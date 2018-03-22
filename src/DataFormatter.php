<?php

namespace SilverStripe\RestfulServer;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\SS_List;

/**
 * A DataFormatter object handles transformation of data from SilverStripe model objects to a particular output
 * format, and vice versa.  This is most commonly used in developing RESTful APIs.
 */
abstract class DataFormatter
{

    use Configurable;

    /**
     * Set priority from 0-100.
     * If multiple formatters for the same extension exist,
     * we select the one with highest priority.
     *
     * @var int
     */
    private static $priority = 50;

    /**
     * Follow relations for the {@link DataObject} instances
     * ($has_one, $has_many, $many_many).
     * Set to "0" to disable relation output.
     *
     * @todo Support more than one nesting level
     *
     * @var int
     */
    public $relationDepth = 1;

    /**
     * Allows overriding of the fields which are rendered for the
     * processed dataobjects. By default, this includes all
     * fields in {@link DataObject::inheritedDatabaseFields()}.
     *
     * @var array
     */
    protected $customFields = null;

    /**
     * Allows addition of fields
     * (e.g. custom getters on a DataObject)
     *
     * @var array
     */
    protected $customAddFields = null;

    /**
     * Allows to limit or add relations.
     * Only use in combination with {@link $relationDepth}.
     * By default, all relations will be shown.
     *
     * @var array
     */
    protected $customRelations = null;

    /**
     * Fields which should be expicitly excluded from the export.
     * Comes in handy for field-level permissions.
     * Will overrule both {@link $customAddFields} and {@link $customFields}
     *
     * @var array
     */
    protected $removeFields = null;

    /**
     * Specifies the mimetype in which all strings
     * returned from the convert*() methods should be used,
     * e.g. "text/xml".
     *
     * @var string
     */
    protected $outputContentType = null;

    /**
     * Used to set totalSize properties on the output
     * of {@link convertDataObjectSet()}, shows the
     * total number of records without the "limit" and "offset"
     * GET parameters. Useful to implement pagination.
     *
     * @var int
     */
    protected $totalSize;

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
     * Get a DataFormatter object suitable for handling the given file extension.
     *
     * @param string $extension
     * @return DataFormatter
     */
    public static function for_extension($extension)
    {
        $classes = ClassInfo::subclassesFor(DataFormatter::class);
        array_shift($classes);
        $sortedClasses = [];
        foreach ($classes as $class) {
            $sortedClasses[$class] = Config::inst()->get($class, 'priority');
        }
        arsort($sortedClasses);
        foreach ($sortedClasses as $className => $priority) {
            $formatter = new $className();
            if (in_array($extension, $formatter->supportedExtensions())) {
                return $formatter;
            }
        }
    }

    /**
     * Get formatter for the first matching extension.
     *
     * @param array $extensions
     * @return DataFormatter
     */
    public static function for_extensions($extensions)
    {
        foreach ($extensions as $extension) {
            if ($formatter = self::for_extension($extension)) {
                return $formatter;
            }
        }

        return false;
    }

    /**
     * Get a DataFormatter object suitable for handling the given mimetype.
     *
     * @param string $mimeType
     * @return DataFormatter
     */
    public static function for_mimetype($mimeType)
    {
        $classes = ClassInfo::subclassesFor(DataFormatter::class);
        array_shift($classes);
        $sortedClasses = [];
        foreach ($classes as $class) {
            $sortedClasses[$class] = Config::inst()->get($class, 'priority');
        }
        arsort($sortedClasses);
        foreach ($sortedClasses as $className => $priority) {
            $formatter = new $className();
            if (in_array($mimeType, $formatter->supportedMimeTypes())) {
                return $formatter;
            }
        }
    }

    /**
     * Get formatter for the first matching mimetype.
     * Useful for HTTP Accept headers which can contain
     * multiple comma-separated mimetypes.
     *
     * @param array $mimetypes
     * @return DataFormatter
     */
    public static function for_mimetypes($mimetypes)
    {
        foreach ($mimetypes as $mimetype) {
            if ($formatter = self::for_mimetype($mimetype)) {
                return $formatter;
            }
        }

        return false;
    }

    /**
     * @param array $fields
     * @return $this
     */
    public function setCustomFields($fields)
    {
        $this->customFields = $fields;
        return $this;
    }

    /**
     * @return array
     */
    public function getCustomFields()
    {
        return $this->customFields;
    }

    /**
     * @param array $fields
     * @return $this
     */
    public function setCustomAddFields($fields)
    {
        $this->customAddFields = $fields;
        return $this;
    }

    /**
     * @param array $relations
     * @return $this
     */
    public function setCustomRelations($relations)
    {
        $this->customRelations = $relations;
        return $this;
    }

    /**
     * @return array
     */
    public function getCustomRelations()
    {
        return $this->customRelations;
    }

    /**
     * @return array
     */
    public function getCustomAddFields()
    {
        return $this->customAddFields;
    }

    /**
     * @param array $fields
     * @return $this
     */
    public function setRemoveFields($fields)
    {
        $this->removeFields = $fields;
        return $this;
    }

    /**
     * @return array
     */
    public function getRemoveFields()
    {
        return $this->removeFields;
    }

    /**
     * @return string
     */
    public function getOutputContentType()
    {
        return $this->outputContentType;
    }

    /**
     * @param int $size
     * @return $this
     */
    public function setTotalSize($size)
    {
        $this->totalSize = (int)$size;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalSize()
    {
        return $this->totalSize;
    }

    /**
     * Returns all fields on the object which should be shown
     * in the output. Can be customised through {@link self::setCustomFields()}.
     *
     * @todo Allow for custom getters on the processed object (currently filtered through inheritedDatabaseFields)
     * @todo Field level permission checks
     *
     * @param DataObject $obj
     * @return array
     */
    protected function getFieldsForObj($obj)
    {
        $dbFields = [];

        // if custom fields are specified, only select these
        if (is_array($this->customFields)) {
            foreach ($this->customFields as $fieldName) {
                // @todo Possible security risk by making methods accessible - implement field-level security
                if (($obj->hasField($fieldName) && !is_object($obj->getField($fieldName)))
                    || $obj->hasMethod("get{$fieldName}")
                ) {
                    $dbFields[$fieldName] = $fieldName;
                }
            }
        } else {
            // by default, all database fields are selected
            $dbFields = DataObject::getSchema()->fieldSpecs(get_class($obj));
            // $dbFields = $obj->inheritedDatabaseFields();
        }

        if (is_array($this->customAddFields)) {
            foreach ($this->customAddFields as $fieldName) {
                // @todo Possible security risk by making methods accessible - implement field-level security
                if ($obj->hasField($fieldName) || $obj->hasMethod("get{$fieldName}")) {
                    $dbFields[$fieldName] = $fieldName;
                }
            }
        }

        // add default required fields
        $dbFields = array_merge($dbFields, ['ID' => 'Int']);

        if (is_array($this->removeFields)) {
            $dbFields = array_diff_key($dbFields, array_combine($this->removeFields, $this->removeFields));
        }

        return $dbFields;
    }

    /**
     * Return an array of the extensions that this data formatter supports
     */
    abstract public function supportedExtensions();

    abstract public function supportedMimeTypes();

    /**
     * Convert a single data object to this format. Return a string.
     *
     * @param DataObjectInterface $do
     * @return mixed
     */
    abstract public function convertDataObject(DataObjectInterface $do);

    /**
     * Convert a data object set to this format. Return a string.
     *
     * @param SS_List $set
     * @return string
     */
    abstract public function convertDataObjectSet(SS_List $set);

    /**
     * Convert an array to this format. Return a string.
     *
     * @param $array
     * @return string
     */
    abstract public function convertArray($array);

    /**
     * @param string $strData HTTP Payload as string
     */
    public function convertStringToArray($strData)
    {
        user_error('DataFormatter::convertStringToArray not implemented on subclass', E_USER_ERROR);
    }

    /**
     * Convert an array of aliased field names to their Dataobject field name
     *
     * @param string $className
     * @param string[] $fields
     * @return string[]
     */
    public function getRealFields($className, $fields)
    {
        $apiMapping = $this->getApiMapping($className);
        if (is_array($apiMapping) && is_array($fields)) {
            $mappedFields = [];
            foreach ($fields as $field) {
                $mappedFields[] = $this->getMappedKey($apiMapping, $field);
            }
            return $mappedFields;
        }
        return $fields;
    }

    /**
     * Get the DataObject field name from its alias
     *
     * @param string $className
     * @param string $field
     * @return string
     */
    public function getRealFieldName($className, $field)
    {
        $apiMapping = $this->getApiMapping($className);
        return $this->getMappedKey($apiMapping, $field);
    }

    /**
     * Get a DataObject Field's Alias
     * defaults to the fieldname
     *
     * @param string $className
     * @param string $field
     * @return string
     */
    public function getFieldAlias($className, $field)
    {
        $apiMapping = $this->getApiMapping($className);
        $apiMapping = array_flip($apiMapping);
        return $this->getMappedKey($apiMapping, $field);
    }

    /**
     * Get the 'api_field_mapping' config value for a class
     * or return an empty array
     *
     * @param string $className
     * @return string[]|array
     */
    protected function getApiMapping($className)
    {
        $apiMapping = Config::inst()->get($className, 'api_field_mapping');
        if ($apiMapping && is_array($apiMapping)) {
            return $apiMapping;
        }
        return [];
    }

    /**
     * Helper function to get mapped field names
     *
     * @param array $map
     * @param string $key
     * @return string
     */
    protected function getMappedKey($map, $key)
    {
        if (is_array($map)) {
            if (array_key_exists($key, $map)) {
                return $map[$key];
            } else {
                return $key;
            }
        }
        return $key;
    }
}
