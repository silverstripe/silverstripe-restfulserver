<?php

namespace SilverStripe\RestfulServer\DataFormatter;

use SilverStripe\RestfulServer\RestfulServer;
use SilverStripe\View\ArrayData;
use SilverStripe\Core\Convert;
use SilverStripe\RestfulServer\DataFormatter;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Control\Director;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\FieldType;

/**
 * Formats a DataObject's member fields into a JSON string
 */
class JSONDataFormatter extends DataFormatter
{
    /**
     * @config
     * @todo pass this from the API to the data formatter somehow
     */
    private static $api_base = "api/v1/";

    protected $outputContentType = 'application/json';

    /**
     * @return array
     */
    public function supportedExtensions()
    {
        return array(
            'json',
            'js'
        );
    }

    /**
     * @return array
     */
    public function supportedMimeTypes()
    {
        return array(
            'application/json',
            'text/x-json'
        );
    }

    /**
     * @param $array
     * @return string
     */
    public function convertArray($array)
    {
        return json_encode($array);
    }

    /**
     * Generate a JSON representation of the given {@link DataObject}.
     *
     * @param DataObject $obj   The object
     * @param Array $fields     If supplied, only fields in the list will be returned
     * @param $relations        Not used
     * @return String JSON
     */
    public function convertDataObject(DataObjectInterface $obj, $fields = null, $relations = null)
    {
        return json_encode($this->convertDataObjectToJSONObject($obj, $fields, $relations));
    }

    /**
     * Internal function to do the conversion of a single data object. It builds an empty object and dynamically
     * adds the properties it needs to it. If it's done as a nested array, json_encode or equivalent won't use
     * JSON object notation { ... }.
     * @param DataObjectInterface $obj
     * @param  $fields
     * @param  $relations
     * @return EmptyJSONObject
     */
    public function convertDataObjectToJSONObject(DataObjectInterface $obj, $fields = null, $relations = null)
    {
        $className = get_class($obj);
        $id = $obj->ID;

        $serobj = ArrayData::array_to_object();

        foreach ($this->getFieldsForObj($obj) as $fieldName => $fieldType) {
            // Field filtering
            if ($fields && !in_array($fieldName, $fields)) {
                continue;
            }

            $fieldValue = self::cast($obj->obj($fieldName));
            $mappedFieldName = $this->getFieldAlias($className, $fieldName);
            $serobj->$mappedFieldName = $fieldValue;
        }

        if ($this->relationDepth > 0) {
            foreach ($obj->hasOne() as $relName => $relClass) {
                if (!singleton($relClass)->stat('api_access')) {
                    continue;
                }

                // Field filtering
                if ($fields && !in_array($relName, $fields)) {
                    continue;
                }
                if ($this->customRelations && !in_array($relName, $this->customRelations)) {
                    continue;
                }
                if ($obj->$relName() && (!$obj->$relName()->exists() || !$obj->$relName()->canView())) {
                    continue;
                }

                $fieldName = $relName . 'ID';
                $rel = $this->config()->api_base;
                $rel .= $obj->$fieldName
                    ? $this->sanitiseClassName($relClass) . '/' . $obj->$fieldName
                    : $this->sanitiseClassName($className) . "/$id/$relName";
                $href = Director::absoluteURL($rel);
                $serobj->$relName = ArrayData::array_to_object(array(
                    "className" => $relClass,
                    "href" => "$href.json",
                    "id" => self::cast($obj->obj($fieldName))
                ));
            }

            foreach ($obj->hasMany() + $obj->manyMany() as $relName => $relClass) {
                $relClass = RestfulServer::parseRelationClass($relClass);

                //remove dot notation from relation names
                $parts = explode('.', $relClass);
                $relClass = array_shift($parts);

                if (!singleton($relClass)->stat('api_access')) {
                    continue;
                }

                // Field filtering
                if ($fields && !in_array($relName, $fields)) {
                    continue;
                }
                if ($this->customRelations && !in_array($relName, $this->customRelations)) {
                    continue;
                }

                $innerParts = array();
                $items = $obj->$relName();
                foreach ($items as $item) {
                    if (!$item->canView()) {
                        continue;
                    }
                    $rel = $this->config()->api_base . $this->sanitiseClassName($relClass) . "/$item->ID";
                    $href = Director::absoluteURL($rel);
                    $innerParts[] = ArrayData::array_to_object(array(
                        "className" => $relClass,
                        "href" => "$href.json",
                        "id" => $item->ID
                    ));
                }
                $serobj->$relName = $innerParts;
            }
        }

        return $serobj;
    }

    /**
     * Generate a JSON representation of the given {@link SS_List}.
     *
     * @param SS_List $set
     * @return String XML
     */
    public function convertDataObjectSet(SS_List $set, $fields = null)
    {
        $items = array();
        foreach ($set as $do) {
            if (!$do->canView()) {
                continue;
            }
            $items[] = $this->convertDataObjectToJSONObject($do, $fields);
        }

        $serobj = ArrayData::array_to_object(array(
            "totalSize" => (is_numeric($this->totalSize)) ? $this->totalSize : null,
            "items" => $items
        ));

        return json_encode($serobj);
    }

    /**
     * @param string $strData
     * @return array|bool|void
     */
    public function convertStringToArray($strData)
    {
        return json_decode($strData, true);
    }

    public static function cast(FieldType\DBField $dbfield)
    {
        switch (true) {
            case $dbfield instanceof FieldType\DBInt:
                return (int)$dbfield->RAW();
            case $dbfield instanceof FieldType\DBFloat:
                return (float)$dbfield->RAW();
            case $dbfield instanceof FieldType\DBBoolean:
                return (bool)$dbfield->RAW();
            case is_null($dbfield->RAW()):
                return null;
        }
        return $dbfield->RAW();
    }
}
