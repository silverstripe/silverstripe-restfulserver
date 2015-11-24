<?php
/**
 * Generic RESTful server, which handles webservice access to arbitrary DataObjects.
 * Relies on serialization/deserialization into different formats provided
 * by the DataFormatter APIs in core.
 * 
 * @todo Finish RestfulServer_Item and RestfulServer_List implementation and re-enable $url_handlers
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
 * 
 * @package framework
 * @subpackage api
 */
class RestfulServer extends Controller
{
    public static $url_handlers = array(
        '$ClassName/$ID/$Relation' => 'handleAction'
        #'$ClassName/#ID' => 'handleItem',
        #'$ClassName' => 'handleList',
    );

    protected static $api_base = "api/v1/";

    protected static $authenticator = 'BasicRestfulAuthenticator';

    /**
     * If no extension is given in the request, resolve to this extension
     * (and subsequently the {@link self::$default_mimetype}.
     *
     * @var string
     */
    public static $default_extension = "xml";
    
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
    
    public static $allowed_actions = array(
        'index'
    );
    
    /*
    function handleItem($request) {
        return new RestfulServer_Item(DataObject::get_by_id($request->param("ClassName"), $request->param("ID")));
    }

    function handleList($request) {
        return new RestfulServer_List(DataObject::get($request->param("ClassName"),""));
    }
    */

    public function init()
    {
        /* This sets up SiteTree the same as when viewing a page through the frontend. Versioned defaults
         * to Stage, and then when viewing the front-end Versioned::choose_site_stage changes it to Live.
         * TODO: In 3.2 we should make the default Live, then change to Stage in the admin area (with a nicer API)
         */
        if (class_exists('SiteTree')) {
            singleton('SiteTree')->extend('modelascontrollerInit', $this);
        }
        parent::init();
    }

    /**
     * This handler acts as the switchboard for the controller.
     * Since no $Action url-param is set, all requests are sent here.
     */
    public function index()
    {
        if (!isset($this->urlParams['ClassName'])) {
            return $this->notFound();
        }
        $className = $this->urlParams['ClassName'];
        $id = (isset($this->urlParams['ID'])) ? $this->urlParams['ID'] : null;
        $relation = (isset($this->urlParams['Relation'])) ? $this->urlParams['Relation'] : null;
        
        // Check input formats
        if (!class_exists($className)) {
            return $this->notFound();
        }
        if ($id && !is_numeric($id)) {
            return $this->notFound();
        }
        if (
            $relation
            && !preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $relation)
            ) {
            return $this->notFound();
        }
        
        // if api access is disabled, don't proceed
        $apiAccess = singleton($className)->stat('api_access');
        if (!$apiAccess) {
            return $this->permissionFailure();
        }

        // authenticate through HTTP BasicAuth
        $this->member = $this->authenticate();

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
     * @param String $className
     * @param Int $id
     * @param String $relation
     * @return String The serialized representation of the requested object(s) - usually XML or JSON.
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
            if (!$obj->canView()) {
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
        $fields = $rawFields ? explode(',', $rawFields) : null;

        if ($obj instanceof SS_List) {
            $responseFormatter->setTotalSize($obj->dataQuery()->query()->unlimitedRowCount());
            $objs = new ArrayList($obj->toArray());
            foreach ($objs as $obj) {
                if (!$obj->canView()) {
                    $objs->remove($obj);
                }
            }
            return $responseFormatter->convertDataObjectSet($objs, $fields);
        } elseif (!$obj) {
            $responseFormatter->setTotalSize(0);
            return $responseFormatter->convertDataObjectSet(new ArrayList(), $fields);
        } else {
            return $responseFormatter->convertDataObject($obj, $fields);
        }
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
    protected function getSearchQuery($className, $params = null, $sort = null,
        $limit = null, $existingQuery = null
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
     * @param String Classname of a DataObject
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
            $className = $this->urlParams['ClassName'];
        }

        // get formatter
        if (!empty($extension)) {
            $formatter = DataFormatter::for_extension($extension);
        } elseif ($includeAcceptHeader && !empty($accept) && $accept != '*/*') {
            $formatter = DataFormatter::for_mimetypes($mimetypes);
            if (!$formatter) {
                $formatter = DataFormatter::for_extension(self::$default_extension);
            }
        } elseif (!empty($contentType)) {
            $formatter = DataFormatter::for_mimetype($contentType);
        } else {
            $formatter = DataFormatter::for_extension(self::$default_extension);
        }

        if (!$formatter) {
            return false;
        }
        
        // set custom fields
        if ($customAddFields = $this->request->getVar('add_fields')) {
            $formatter->setCustomAddFields(explode(',', $customAddFields));
        }
        if ($customFields = $this->request->getVar('fields')) {
            $formatter->setCustomFields(explode(',', $customFields));
        }
        $formatter->setCustomRelations($this->getAllowedRelations($className));
        
        $apiAccess = singleton($className)->stat('api_access');
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
     * @param String Classname of a DataObject
     * @return DataFormatter
     */
    protected function getRequestDataFormatter($className = null)
    {
        return $this->getDataFormatter(false, $className);
    }
    
    /**
     * @param String Classname of a DataObject
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
        if (!$obj->canDelete()) {
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
        if (!$obj->canEdit()) {
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
        
        $obj = $this->updateDataObject($obj, $reqFormatter);
        
        $this->getResponse()->setStatusCode(200); // Success
        $this->getResponse()->addHeader('Content-Type', $responseFormatter->getOutputContentType());

        // Append the default extension for the output format to the Location header
        // or else we'll use the default (XML)
        $types = $responseFormatter->supportedExtensions();
        $type = '';
        if (count($types)) {
            $type = ".{$types[0]}";
        }

        $objHref = Director::absoluteURL(self::$api_base . "$obj->class/$obj->ID" . $type);
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
            
            if (!$obj->hasMethod($relation)) {
                return $this->notFound();
            }
            
            if (!$obj->stat('allowed_actions') || !in_array($relation, $obj->stat('allowed_actions'))) {
                return $this->permissionFailure();
            }
            
            $obj->$relation();
            
            $this->getResponse()->setStatusCode(204); // No Content
            return true;
        } else {
            if (!singleton($className)->canCreate()) {
                return $this->permissionFailure();
            }
            $obj = new $className();
        
            $reqFormatter = $this->getRequestDataFormatter($className);
            if (!$reqFormatter) {
                return $this->unsupportedMediaType();
            }
        
            $responseFormatter = $this->getResponseDataFormatter($className);
        
            $obj = $this->updateDataObject($obj, $reqFormatter);
        
            $this->getResponse()->setStatusCode(201); // Created
            $this->getResponse()->addHeader('Content-Type', $responseFormatter->getOutputContentType());

            // Append the default extension for the output format to the Location header
            // or else we'll use the default (XML)
            $types = $responseFormatter->supportedExtensions();
            $type = '';
            if (count($types)) {
                $type = ".{$types[0]}";
            }

            $objHref = Director::absoluteURL(self::$api_base . "$obj->class/$obj->ID" . $type);
            $this->getResponse()->addHeader('Location', $objHref);
        
            return $responseFormatter->convertDataObject($obj);
        }
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
     * @return DataObject The passed object
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
            $data = $formatter->convertStringToArray($body);
        } else {
            // assume application/x-www-form-urlencoded which is automatically parsed by PHP
            $data = $this->request->postVars();
        }
        
        // @todo Disallow editing of certain keys in database
        $data = array_diff_key($data, array('ID', 'Created'));
        
        $apiAccess = singleton($this->urlParams['ClassName'])->stat('api_access');
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
        return DataList::create($className)->byIDs(array($id));
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
            if ($relationClass = $obj->has_one($relationName)) {
                $joinField = $relationName . 'ID';
                $list = DataList::create($relationClass)->byIDs(array($obj->$joinField));
            } else {
                $list = $obj->$relationName();
            }
            
            $apiAccess = singleton($list->dataClass())->stat('api_access');
            if (!$apiAccess) {
                return false;
            }
            
            return $this->getSearchQuery($list->dataClass(), $params, $sort, $limit, $list);
        }
    }
    
    protected function permissionFailure()
    {
        // return a 401
        $this->getResponse()->setStatusCode(401);
        $this->getResponse()->addHeader('WWW-Authenticate', 'Basic realm="API Access"');
        $this->getResponse()->addHeader('Content-Type', 'text/plain');
        return "You don't have access to this item through the API.";
    }

    protected function notFound()
    {
        // return a 404
        $this->getResponse()->setStatusCode(404);
        $this->getResponse()->addHeader('Content-Type', 'text/plain');
        return "That object wasn't found";
    }
    
    protected function methodNotAllowed()
    {
        $this->getResponse()->setStatusCode(405);
        $this->getResponse()->addHeader('Content-Type', 'text/plain');
        return "Method Not Allowed";
    }
    
    protected function unsupportedMediaType()
    {
        $this->response->setStatusCode(415); // Unsupported Media Type
        $this->getResponse()->addHeader('Content-Type', 'text/plain');
        return "Unsupported Media Type";
    }
    
    /**
     * A function to authenticate a user
     *
     * @return Member|false the logged in member
     */
    protected function authenticate()
    {
        $authClass = self::config()->authenticator;
        return $authClass::authenticate();
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
        $allowedRelations = array();
        $obj = singleton($class);
        $relations = (array)$obj->has_one() + (array)$obj->has_many() + (array)$obj->many_many();
        if ($relations) {
            foreach ($relations as $relName => $relClass) {
                if (singleton($relClass)->stat('api_access')) {
                    $allowedRelations[] = $relName;
                }
            }
        }
        return $allowedRelations;
    }
}

/**
 * Restful server handler for a SS_List
 * 
 * @package framework
 * @subpackage api
 */
class RestfulServer_List
{
    public static $url_handlers = array(
        '#ID' => 'handleItem',
    );

    public function __construct($list)
    {
        $this->list = $list;
    }
    
    public function handleItem($request)
    {
        return new RestulServer_Item($this->list->getById($request->param('ID')));
    }
}

/**
 * Restful server handler for a single DataObject
 * 
 * @package framework
 * @subpackage api
 */
class RestfulServer_Item
{
    public static $url_handlers = array(
        '$Relation' => 'handleRelation',
    );

    public function __construct($item)
    {
        $this->item = $item;
    }
    
    public function handleRelation($request)
    {
        $funcName = $request('Relation');
        $relation = $this->item->$funcName();

        if ($relation instanceof SS_List) {
            return new RestfulServer_List($relation);
        } else {
            return new RestfulServer_Item($relation);
        }
    }
}
