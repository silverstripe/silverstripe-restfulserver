<?php

namespace SilverStripe\RestfulServer;

use SilverStripe\ORM\ArrayList;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;

/**
 * Generic RESTful server, which handles webservice access to arbitrary DataObjects.
 * Relies on serialization/deserialization into different formats provided
 * by the DataFormatter APIs in core.
 *
 * @todo Implement PUT/POST/DELETE for relations
 * @todo Access-Control for relations (you might be allowed to view Members and Groups,
 *       but not their relation with each other)
 * @todo Make SearchContext specification customizeable for each class
 * @todo Allow for range-searches (e.g. on Created column)
 * @todo Filter relation listings by $api_access and canView() permissions
 * @todo Exclude relations when "fields" are specified through URL (they should be explicitly
 *       requested in this case)
 * @todo Custom filters per DataObject subclass, e.g. to disallow showing unpublished pages in
 * SiteTree/Versioned/Hierarchy
 * @todo URL parameter namespacing for search-fields, limit, fields, add_fields
 *       (might all be valid dataobject properties)
 *       e.g. you wouldn't be able to search for a "limit" property on your subclass as
 *       its overlayed with the search logic
 * @todo i18n integration (e.g. Page/1.xml?lang=de_DE)
 * @todo Access to extendable methods/relations like SiteTree/1/Versions or SiteTree/1/Version/22
 * @todo Respect $api_access array notation in search contexts
 */
class RestfulServer extends Controller
{
    /**
     * @config
     * @var array
     */
    private static $url_handlers = array(
        '$ClassName!/$ID/$Relation' => 'handleAction',
        '' => 'notFound'
    );

    /**
     * @config
     * @var string root of the api route, MUST have a trailing slash
     */
    private static $api_base = "api/v1/";

    /**
     * @config
     * @var string Class name for an authenticator to use on API access
     */
    private static $authenticator = BasicRestfulAuthenticator::class;

    /**
     * If no extension is given in the request, resolve to this extension
     * (and subsequently the {@link self::$default_mimetype}.
     *
     * @config
     * @var string
     */
    private static $default_extension = "xml";

    /**
     * Whether or not to send an additional "Location" header for POST requests
     * to satisfy HTTP 1.1: https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     *
     * Note: With this enabled (the default), no POST request for resource creation
     * will return an HTTP 201. Because of the addition of the "Location" header,
     * all responses become a straight HTTP 200.
     *
     * @config
     * @var boolean
     */
    private static $location_header_on_create = true;

    /**
     * If no extension is given, resolve the request to this mimetype.
     *
     * @var string
     */
    protected static $default_mimetype = "text/xml";

    /**
     * @uses authenticate()
     * @var Member
     */
    protected $member;

    private static $allowed_actions = array(
        'index',
        'notFound'
    );

    public function init()
    {
        /* This sets up SiteTree the same as when viewing a page through the frontend. Versioned defaults
         * to Stage, and then when viewing the front-end Versioned::choose_site_stage changes it to Live.
         * TODO: In 3.2 we should make the default Live, then change to Stage in the admin area (with a nicer API)
         */
        if (class_exists(SiteTree::class)) {
            singleton(SiteTree::class)->extend('modelascontrollerInit', $this);
        }
        parent::init();
    }

    /**
     * Backslashes in fully qualified class names (e.g. NameSpaced\ClassName)
     * kills both requests (i.e. URIs) and XML (invalid character in a tag name)
     * So we'll replace them with a hyphen (-), as it's also unambiguious
     * in both cases (invalid in a php class name, and safe in an xml tag name)
     *
     * @param string $classname
     * @return string 'escaped' class name
     */
    protected function sanitiseClassName($className)
    {
        return str_replace('\\', '-', $className);
    }

    /**
     * Convert hyphen escaped class names back into fully qualified
     * PHP safe variant.
     *
     * @param string $classname
     * @return string syntactically valid classname
     */
    protected function unsanitiseClassName($className)
    {
        return str_replace('-', '\\', $className);
    }

    /**
     * Parse many many relation class (works with through array syntax)
     *
     * @param string|array $class
     * @return string|array
     */
    public static function parseRelationClass($class)
    {
        // detect many many through syntax
        if (is_array($class)
            && array_key_exists('through', $class)
            && array_key_exists('to', $class)
        ) {
            $toRelation = $class['to'];

            $hasOne = Config::inst()->get($class['through'], 'has_one');
            if (empty($hasOne) || !is_array($hasOne) || !array_key_exists($toRelation, $hasOne)) {
                return $class;
            }

            return $hasOne[$toRelation];
        }

        return $class;
    }

    /**
     * This handler acts as the switchboard for the controller.
     * Since no $Action url-param is set, all requests are sent here.
     */
    public function index(HTTPRequest $request)
    {
        $className = $this->unsanitiseClassName($request->param('ClassName'));
        $id = $request->param('ID') ?: null;
        $relation = $request->param('Relation') ?: null;

        // Check input formats
        if (!class_exists($className)) {
            return $this->notFound();
        }
        if ($id && !is_numeric($id)) {
            return $this->notFound();
        }
        if ($relation
            && !preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $relation)
        ) {
            return $this->notFound();
        }

        // if api access is disabled, don't proceed
        $apiAccess = Config::inst()->get($className, 'api_access');
        if (!$apiAccess) {
            return $this->permissionFailure();
        }

        // authenticate through HTTP BasicAuth
        $this->member = $this->authenticate();

        try {
            // handle different HTTP verbs
            if ($this->request->isGET() || $this->request->isHEAD()) {
                return $this->getHandler($className, $id, $relation);
            }

            if ($this->request->isPOST()) {
                return $this->postHandler($className, $id, $relation);
            }

            if ($this->request->isPUT()) {
                return $this->putHandler($className, $id, $relation);
            }

            if ($this->request->isDELETE()) {
                return $this->deleteHandler($className, $id, $relation);
            }
        } catch (\Exception $e) {
            return $this->exceptionThrown($this->getRequestDataFormatter($className), $e);
        }

        // if no HTTP verb matches, return error
        return $this->methodNotAllowed();
    }

    /**
     * Handler for object read.
     *
     * The data object will be returned in the following format:
     *
     * <ClassName>
     *   <FieldName>Value</FieldName>
     *   ...
     *   <HasOneRelName id="ForeignID" href="LinkToForeignRecordInAPI" />
     *   ...
     *   <HasManyRelName>
     *     <ForeignClass id="ForeignID" href="LinkToForeignRecordInAPI" />
     *     <ForeignClass id="ForeignID" href="LinkToForeignRecordInAPI" />
     *   </HasManyRelName>
     *   ...
     *   <ManyManyRelName>
     *     <ForeignClass id="ForeignID" href="LinkToForeignRecordInAPI" />
     *     <ForeignClass id="ForeignID" href="LinkToForeignRecordInAPI" />
     *   </ManyManyRelName>
     * </ClassName>
     *
     * Access is controlled by two variables:
     *
     *   - static $api_access must be set. This enables the API on a class by class basis
     *   - $obj->canView() must return true. This lets you implement record-level security
     *
     * @todo Access checking
     *
     * @param string $className
     * @param Int $id
     * @param string $relation
     * @return string The serialized representation of the requested object(s) - usually XML or JSON.
     */
    protected function getHandler($className, $id, $relationName)
    {
        $sort = '';

        if ($this->request->getVar('sort')) {
            $dir = $this->request->getVar('dir');
            $sort = array($this->request->getVar('sort') => ($dir ? $dir : 'ASC'));
        }

        $limit = array(
            'start' => $this->request->getVar('start'),
            'limit' => $this->request->getVar('limit')
        );

        $params = $this->request->getVars();

        $responseFormatter = $this->getResponseDataFormatter($className);
        if (!$responseFormatter) {
            return $this->unsupportedMediaType();
        }

        // $obj can be either a DataObject or a SS_List,
        // depending on the request
        if ($id) {
            // Format: /api/v1/<MyClass>/<ID>
            $obj = $this->getObjectQuery($className, $id, $params)->First();
            if (!$obj) {
                return $this->notFound();
            }
            if (!$obj->canView($this->getMember())) {
                return $this->permissionFailure();
            }

            // Format: /api/v1/<MyClass>/<ID>/<Relation>
            if ($relationName) {
                $obj = $this->getObjectRelationQuery($obj, $params, $sort, $limit, $relationName);
                if (!$obj) {
                    return $this->notFound();
                }

                // TODO Avoid creating data formatter again for relation class (see above)
                $responseFormatter = $this->getResponseDataFormatter($obj->dataClass());
            }
        } else {
            // Format: /api/v1/<MyClass>
            $obj = $this->getObjectsQuery($className, $params, $sort, $limit);
        }

        $this->getResponse()->addHeader('Content-Type', $responseFormatter->getOutputContentType());

        $rawFields = $this->request->getVar('fields');
        $realFields = $responseFormatter->getRealFields($className, explode(',', $rawFields));
        $fields = $rawFields ? $realFields : null;

        if ($obj instanceof SS_List) {
            $objs = ArrayList::create($obj->toArray());
            foreach ($objs as $obj) {
                if (!$obj->canView($this->getMember())) {
                    $objs->remove($obj);
                }
            }
            $responseFormatter->setTotalSize($objs->count());
            $this->extend('updateRestfulGetHandler', $objs, $responseFormatter);

            return $responseFormatter->convertDataObjectSet($objs, $fields);
        }

        if (!$obj) {
            $responseFormatter->setTotalSize(0);
            return $responseFormatter->convertDataObjectSet(new ArrayList(), $fields);
        }

        $this->extend('updateRestfulGetHandler', $obj, $responseFormatter);

        return $responseFormatter->convertDataObject($obj, $fields);
    }

    /**
     * Uses the default {@link SearchContext} specified through
     * {@link DataObject::getDefaultSearchContext()} to augument
     * an existing query object (mostly a component query from {@link DataObject})
     * with search clauses.
     *
     * @todo Allow specifying of different searchcontext getters on model-by-model basis
     *
     * @param string $className
     * @param array $params
     * @return SS_List
     */
    protected function getSearchQuery(
        $className,
        $params = null,
        $sort = null,
        $limit = null,
        $existingQuery = null
    ) {
        if (singleton($className)->hasMethod('getRestfulSearchContext')) {
            $searchContext = singleton($className)->{'getRestfulSearchContext'}();
        } else {
            $searchContext = singleton($className)->getDefaultSearchContext();
        }
        return $searchContext->getQuery($params, $sort, $limit, $existingQuery);
    }

    /**
     * Returns a dataformatter instance based on the request
     * extension or mimetype. Falls back to {@link self::$default_extension}.
     *
     * @param boolean $includeAcceptHeader Determines wether to inspect and prioritize any HTTP Accept headers
     * @param string Classname of a DataObject
     * @return DataFormatter
     */
    protected function getDataFormatter($includeAcceptHeader = false, $className = null)
    {
        $extension = $this->request->getExtension();
        $contentTypeWithEncoding = $this->request->getHeader('Content-Type');
        preg_match('/([^;]*)/', $contentTypeWithEncoding, $contentTypeMatches);
        $contentType = $contentTypeMatches[0];
        $accept = $this->request->getHeader('Accept');
        $mimetypes = $this->request->getAcceptMimetypes();
        if (!$className) {
            $className = $this->unsanitiseClassName($this->request->param('ClassName'));
        }

        // get formatter
        if (!empty($extension)) {
            $formatter = DataFormatter::for_extension($extension);
        } elseif ($includeAcceptHeader && !empty($accept) && strpos($accept, '*/*') === false) {
            $formatter = DataFormatter::for_mimetypes($mimetypes);
            if (!$formatter) {
                $formatter = DataFormatter::for_extension($this->config()->default_extension);
            }
        } elseif (!empty($contentType)) {
            $formatter = DataFormatter::for_mimetype($contentType);
        } else {
            $formatter = DataFormatter::for_extension($this->config()->default_extension);
        }

        if (!$formatter) {
            return false;
        }

        // set custom fields
        if ($customAddFields = $this->request->getVar('add_fields')) {
            $customAddFields = $formatter->getRealFields($className, explode(',', $customAddFields));
            $formatter->setCustomAddFields($customAddFields);
        }
        if ($customFields = $this->request->getVar('fields')) {
            $customFields = $formatter->getRealFields($className, explode(',', $customFields));
            $formatter->setCustomFields($customFields);
        }
        $formatter->setCustomRelations($this->getAllowedRelations($className));

        $apiAccess = Config::inst()->get($className, 'api_access');
        if (is_array($apiAccess)) {
            $formatter->setCustomAddFields(
                array_intersect((array)$formatter->getCustomAddFields(), (array)$apiAccess['view'])
            );
            if ($formatter->getCustomFields()) {
                $formatter->setCustomFields(
                    array_intersect((array)$formatter->getCustomFields(), (array)$apiAccess['view'])
                );
            } else {
                $formatter->setCustomFields((array)$apiAccess['view']);
            }
            if ($formatter->getCustomRelations()) {
                $formatter->setCustomRelations(
                    array_intersect((array)$formatter->getCustomRelations(), (array)$apiAccess['view'])
                );
            } else {
                $formatter->setCustomRelations((array)$apiAccess['view']);
            }
        }

        // set relation depth
        $relationDepth = $this->request->getVar('relationdepth');
        if (is_numeric($relationDepth)) {
            $formatter->relationDepth = (int)$relationDepth;
        }

        return $formatter;
    }

    /**
     * @param string Classname of a DataObject
     * @return DataFormatter
     */
    protected function getRequestDataFormatter($className = null)
    {
        return $this->getDataFormatter(false, $className);
    }

    /**
     * @param string Classname of a DataObject
     * @return DataFormatter
     */
    protected function getResponseDataFormatter($className = null)
    {
        return $this->getDataFormatter(true, $className);
    }

    /**
     * Handler for object delete
     */
    protected function deleteHandler($className, $id)
    {
        $obj = DataObject::get_by_id($className, $id);
        if (!$obj) {
            return $this->notFound();
        }
        if (!$obj->canDelete($this->getMember())) {
            return $this->permissionFailure();
        }

        $obj->delete();

        $this->getResponse()->setStatusCode(204); // No Content
        return true;
    }

    /**
     * Handler for object write
     */
    protected function putHandler($className, $id)
    {
        $obj = DataObject::get_by_id($className, $id);
        if (!$obj) {
            return $this->notFound();
        }

        if (!$obj->canEdit($this->getMember())) {
            return $this->permissionFailure();
        }

        $reqFormatter = $this->getRequestDataFormatter($className);
        if (!$reqFormatter) {
            return $this->unsupportedMediaType();
        }

        $responseFormatter = $this->getResponseDataFormatter($className);
        if (!$responseFormatter) {
            return $this->unsupportedMediaType();
        }

        try {
            /** @var DataObject|string */
            $obj = $this->updateDataObject($obj, $reqFormatter);
        } catch (ValidationException $e) {
            return $this->validationFailure($responseFormatter, $e->getResult());
        }

        if (is_string($obj)) {
            return $obj;
        }

        $this->getResponse()->setStatusCode(202); // Accepted
        $this->getResponse()->addHeader('Content-Type', $responseFormatter->getOutputContentType());

        // Append the default extension for the output format to the Location header
        // or else we'll use the default (XML)
        $types = $responseFormatter->supportedExtensions();
        $type = '';
        if (count($types)) {
            $type = ".{$types[0]}";
        }

        $urlSafeClassName = $this->sanitiseClassName(get_class($obj));
        $apiBase = $this->config()->api_base;
        $objHref = Director::absoluteURL($apiBase . "$urlSafeClassName/$obj->ID" . $type);
        $this->getResponse()->addHeader('Location', $objHref);

        return $responseFormatter->convertDataObject($obj);
    }

    /**
     * Handler for object append / method call.
     *
     * @todo Posting to an existing URL (without a relation)
     * current resolves in creatig a new element,
     * rather than a "Conflict" message.
     */
    protected function postHandler($className, $id, $relation)
    {
        if ($id) {
            if (!$relation) {
                $this->response->setStatusCode(409);
                return 'Conflict';
            }

            $obj = DataObject::get_by_id($className, $id);
            if (!$obj) {
                return $this->notFound();
            }

            $reqFormatter = $this->getRequestDataFormatter($className);
            if (!$reqFormatter) {
                return $this->unsupportedMediaType();
            }

            $relation = $reqFormatter->getRealFieldName($className, $relation);

            if (!$obj->hasMethod($relation)) {
                return $this->notFound();
            }

            if (!Config::inst()->get($className, 'allowed_actions') ||
                !in_array($relation, Config::inst()->get($className, 'allowed_actions'))) {
                return $this->permissionFailure();
            }

            $obj->$relation();

            $this->getResponse()->setStatusCode(204); // No Content
            return true;
        }

        if (!singleton($className)->canCreate($this->getMember())) {
            return $this->permissionFailure();
        }

        $obj = Injector::inst()->create($className);

        $reqFormatter = $this->getRequestDataFormatter($className);
        if (!$reqFormatter) {
            return $this->unsupportedMediaType();
        }

        $responseFormatter = $this->getResponseDataFormatter($className);

        try {
            /** @var DataObject|string $obj */
            $obj = $this->updateDataObject($obj, $reqFormatter);
        } catch (ValidationException $e) {
            return $this->validationFailure($responseFormatter, $e->getResult());
        }

        if (is_string($obj)) {
            return $obj;
        }

        $this->getResponse()->setStatusCode(201); // Created
        $this->getResponse()->addHeader('Content-Type', $responseFormatter->getOutputContentType());

        // Append the default extension for the output format to the Location header
        // or else we'll use the default (XML)
        $types = $responseFormatter->supportedExtensions();
        $type = '';
        if (count($types)) {
            $type = ".{$types[0]}";
        }

        // Deviate slightly from the spec: Helps datamodel API access restrict
        // to consulting just canCreate(), not canView() as a result of the additional
        // "Location" header.
        if ($this->config()->get('location_header_on_create')) {
            $urlSafeClassName = $this->sanitiseClassName(get_class($obj));
            $apiBase = $this->config()->api_base;
            $objHref = Director::absoluteURL($apiBase . "$urlSafeClassName/$obj->ID" . $type);
            $this->getResponse()->addHeader('Location', $objHref);
        }

        return $responseFormatter->convertDataObject($obj);
    }

    /**
     * Converts either the given HTTP Body into an array
     * (based on the DataFormatter instance), or returns
     * the POST variables.
     * Automatically filters out certain critical fields
     * that shouldn't be set by the client (e.g. ID).
     *
     * @param DataObject $obj
     * @param DataFormatter $formatter
     * @return DataObject|string The passed object, or "No Content" if incomplete input data is provided
     */
    protected function updateDataObject($obj, $formatter)
    {
        // if neither an http body nor POST data is present, return error
        $body = $this->request->getBody();
        if (!$body && !$this->request->postVars()) {
            $this->getResponse()->setStatusCode(204); // No Content
            return 'No Content';
        }

        if (!empty($body)) {
            $rawdata = $formatter->convertStringToArray($body);
        } else {
            // assume application/x-www-form-urlencoded which is automatically parsed by PHP
            $rawdata = $this->request->postVars();
        }

        $className = $this->unsanitiseClassName($this->request->param('ClassName'));
        // update any aliased field names
        $data = [];
        foreach ($rawdata as $key => $value) {
            $newkey = $formatter->getRealFieldName($className, $key);
            $data[$newkey] = $value;
        }

        // @todo Disallow editing of certain keys in database
        $data = array_diff_key($data, ['ID', 'Created']);

        $apiAccess = singleton($className)->config()->api_access;
        if (is_array($apiAccess) && isset($apiAccess['edit'])) {
            $data = array_intersect_key($data, array_combine($apiAccess['edit'], $apiAccess['edit']));
        }

        $obj->update($data);
        $obj->write();

        return $obj;
    }

    /**
     * Gets a single DataObject by ID,
     * through a request like /api/v1/<MyClass>/<MyID>
     *
     * @param string $className
     * @param int $id
     * @param array $params
     * @return DataList
     */
    protected function getObjectQuery($className, $id, $params)
    {
        return DataList::create($className)->byIDs([$id]);
    }

    /**
     * @param DataObject $obj
     * @param array $params
     * @param int|array $sort
     * @param int|array $limit
     * @return SQLQuery
     */
    protected function getObjectsQuery($className, $params, $sort, $limit)
    {
        return $this->getSearchQuery($className, $params, $sort, $limit);
    }


    /**
     * @param DataObject $obj
     * @param array $params
     * @param int|array $sort
     * @param int|array $limit
     * @param string $relationName
     * @return SQLQuery|boolean
     */
    protected function getObjectRelationQuery($obj, $params, $sort, $limit, $relationName)
    {
        // The relation method will return a DataList, that getSearchQuery subsequently manipulates
        if ($obj->hasMethod($relationName)) {
            // $this->HasOneName() will return a dataobject or null, neither
            // of which helps us get the classname in a consistent fashion.
            // So we must use a way that is reliable.
            if ($relationClass = DataObject::getSchema()->hasOneComponent(get_class($obj), $relationName)) {
                $joinField = $relationName . 'ID';
                // Again `byID` will return the wrong type for our purposes. So use `byIDs`
                $list = DataList::create($relationClass)->byIDs([$obj->$joinField]);
            } else {
                $list = $obj->$relationName();
            }

            $apiAccess = Config::inst()->get($list->dataClass(), 'api_access');


            if (!$apiAccess) {
                return false;
            }

            return $this->getSearchQuery($list->dataClass(), $params, $sort, $limit, $list);
        }
    }

    /**
     * @return string
     */
    protected function permissionFailure()
    {
        // return a 401
        $this->getResponse()->setStatusCode(401);
        $this->getResponse()->addHeader('WWW-Authenticate', 'Basic realm="API Access"');
        $this->getResponse()->addHeader('Content-Type', 'text/plain');

        $response = "You don't have access to this item through the API.";
        $this->extend(__FUNCTION__, $response);

        return $response;
    }

    /**
     * @return string
     */
    protected function notFound()
    {
        // return a 404
        $this->getResponse()->setStatusCode(404);
        $this->getResponse()->addHeader('Content-Type', 'text/plain');

        $response = "That object wasn't found";
        $this->extend(__FUNCTION__, $response);

        return $response;
    }

    /**
     * @return string
     */
    protected function methodNotAllowed()
    {
        $this->getResponse()->setStatusCode(405);
        $this->getResponse()->addHeader('Content-Type', 'text/plain');

        $response = "Method Not Allowed";
        $this->extend(__FUNCTION__, $response);

        return $response;
    }

    /**
     * @return string
     */
    protected function unsupportedMediaType()
    {
        $this->response->setStatusCode(415); // Unsupported Media Type
        $this->getResponse()->addHeader('Content-Type', 'text/plain');

        $response = "Unsupported Media Type";
        $this->extend(__FUNCTION__, $response);

        return $response;
    }

    /**
     * @param ValidationResult $result
     * @return mixed
     */
    protected function validationFailure(DataFormatter $responseFormatter, ValidationResult $result)
    {
        $this->getResponse()->setStatusCode(400);
        $this->getResponse()->addHeader('Content-Type', $responseFormatter->getOutputContentType());

        $response = [
            'type' => ValidationException::class,
            'messages' => $result->getMessages(),
        ];

        $this->extend(__FUNCTION__, $response, $result);

        return $responseFormatter->convertArray($response);
    }

    /**
     * @param DataFormatter $responseFormatter
     * @param \Exception $e
     * @return string
     */
    protected function exceptionThrown(DataFormatter $responseFormatter, \Exception $e)
    {
        $this->getResponse()->setStatusCode(500);
        $this->getResponse()->addHeader('Content-Type', $responseFormatter->getOutputContentType());

        $response = [
            'type' => get_class($e),
            'message' => $e->getMessage(),
        ];

        $this->extend(__FUNCTION__, $response, $e);

        return $responseFormatter->convertArray($response);
    }

    /**
     * A function to authenticate a user
     *
     * @return Member|false the logged in member
     */
    protected function authenticate()
    {
        $authClass = $this->config()->authenticator;
        $member = $authClass::authenticate();
        Security::setCurrentUser($member);
        return $member;
    }

    /**
     * Return only relations which have $api_access enabled.
     * @todo Respect field level permissions once they are available in core
     *
     * @param string $class
     * @param Member $member
     * @return array
     */
    protected function getAllowedRelations($class, $member = null)
    {
        $allowedRelations = [];
        $obj = singleton($class);
        $relations = (array)$obj->hasOne() + (array)$obj->hasMany() + (array)$obj->manyMany();
        if ($relations) {
            foreach ($relations as $relName => $relClass) {
                $relClass = static::parseRelationClass($relClass);

                //remove dot notation from relation names
                $parts = explode('.', $relClass);
                $relClass = array_shift($parts);
                if (Config::inst()->get($relClass, 'api_access')) {
                    $allowedRelations[] = $relName;
                }
            }
        }
        return $allowedRelations;
    }

    /**
     * Get the current Member, if available
     *
     * @return Member|null
     */
    protected function getMember()
    {
        return Security::getCurrentUser();
    }
}
