<?php

namespace SilverStripe\RestfulServer\DataFormatter;

use SimpleXMLElement;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\Debug;
use SilverStripe\RestfulServer\DataFormatter;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Control\Director;
use SilverStripe\ORM\SS_List;
use SilverStripe\RestfulServer\RestfulServer;
use InvalidArgumentException;

/**
 * Formats a DataObject's member fields into an XML string
 */
class XMLDataFormatter extends DataFormatter
{

    /**
     * @config
     * @todo pass this from the API to the data formatter somehow
     */
    private static $api_base = "api/v1/";

    protected $outputContentType = 'text/xml';

    /**
     * @return array
     */
    public function supportedExtensions()
    {
        return array(
            'xml'
        );
    }

    /**
     * @return array
     */
    public function supportedMimeTypes()
    {
        return array(
            'text/xml',
            'application/xml',
        );
    }

    /**
     * @param $array
     * @return string
     * @throws \Exception
     */
    public function convertArray($array)
    {
        $response = Controller::curr()->getResponse();
        if ($response) {
            $response->addHeader("Content-Type", "text/xml");
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n
            <response>{$this->convertArrayWithoutHeader($array)}</response>";
    }

    /**
     * @param $array
     * @return string
     * @throws \Exception
     */
    public function convertArrayWithoutHeader($array)
    {
        $xml = '';

        foreach ($array as $fieldName => $fieldValue) {
            if (is_array($fieldValue)) {
                if (is_numeric($fieldName)) {
                    $fieldName = 'Item';
                }

                $xml .= "<{$fieldName}>\n";
                $xml .= $this->convertArrayWithoutHeader($fieldValue);
                $xml .= "</{$fieldName}>\n";
            } else {
                $xml .= "<$fieldName>$fieldValue</$fieldName>\n";
            }
        }

        return $xml;
    }

    /**
     * Generate an XML representation of the given {@link DataObject}.
     *
     * @param DataObject $obj
     * @param $includeHeader Include <?xml ...?> header (Default: true)
     * @return String XML
     */
    public function convertDataObject(DataObjectInterface $obj, $fields = null)
    {
        $response = Controller::curr()->getResponse();
        if ($response) {
            $response->addHeader("Content-Type", "text/xml");
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . $this->convertDataObjectWithoutHeader($obj, $fields);
    }

    /**
     * @param DataObject $obj
     * @param null $fields
     * @param null $relations
     * @return string
     */
    public function convertDataObjectWithoutHeader(DataObject $obj, $fields = null, $relations = null)
    {
        $className = $this->sanitiseClassName(get_class($obj));
        $id = $obj->ID;
        $objHref = Director::absoluteURL($this->config()->api_base . "$className/$obj->ID");

        $xml = "<$className href=\"$objHref.xml\">\n";
        foreach ($this->getFieldsForObj($obj) as $fieldName => $fieldType) {
            // Field filtering
            if ($fields && !in_array($fieldName, $fields ?? [])) {
                continue;
            }
            $fieldValue = $obj->obj($fieldName)->forTemplate();
            if (!mb_check_encoding($fieldValue, 'utf-8')) {
                $fieldValue = "(data is badly encoded)";
            }

            if (is_object($fieldValue) && is_subclass_of($fieldValue, 'Object') && $fieldValue->hasMethod('toXML')) {
                $xml .= $fieldValue->toXML();
            } else {
                if ('HTMLText' == $fieldType) {
                    // Escape HTML values using CDATA
                    $fieldValue = sprintf('<![CDATA[%s]]>', str_replace(']]>', ']]]]><![CDATA[>', $fieldValue ?? ''));
                } else {
                    $fieldValue = Convert::raw2xml($fieldValue);
                }
                $mappedFieldName = $this->getFieldAlias(get_class($obj), $fieldName);
                $xml .= "<$mappedFieldName>$fieldValue</$mappedFieldName>\n";
            }
        }

        if ($this->relationDepth > 0) {
            foreach ($obj->hasOne() as $relName => $relClass) {
                if (!singleton($relClass)::config()->get('api_access')) {
                    continue;
                }
                // backslashes in FQCNs kills both URIs and XML
                $relClass = $this->sanitiseClassName($relClass);

                // Field filtering
                if ($fields && !in_array($relName, $fields ?? [])) {
                    continue;
                }
                if ($this->customRelations && !in_array($relName, $this->customRelations ?? [])) {
                    continue;
                }

                $fieldName = $relName . 'ID';
                if ($obj->$fieldName) {
                    $href = Director::absoluteURL($this->config()->api_base . "$relClass/" . $obj->$fieldName);
                } else {
                    $href = Director::absoluteURL($this->config()->api_base . "$className/$id/$relName");
                }
                $xml .= "<$relName linktype=\"has_one\" href=\"$href.xml\" id=\"" . $obj->$fieldName
                    . "\"></$relName>\n";
            }

            foreach ($obj->hasMany() as $relName => $relClass) {
                //remove dot notation from relation names
                $parts = explode('.', $relClass ?? '');
                $relClass = array_shift($parts);
                if (!singleton($relClass)::config()->get('api_access')) {
                    continue;
                }
                // backslashes in FQCNs kills both URIs and XML
                $relClass = $this->sanitiseClassName($relClass);

                // Field filtering
                if ($fields && !in_array($relName, $fields ?? [])) {
                    continue;
                }
                if ($this->customRelations && !in_array($relName, $this->customRelations ?? [])) {
                    continue;
                }

                $xml .= "<$relName linktype=\"has_many\" href=\"$objHref/$relName.xml\">\n";
                $items = $obj->$relName();
                if ($items) {
                    foreach ($items as $item) {
                        $href = Director::absoluteURL($this->config()->api_base . "$relClass/$item->ID");
                        $xml .= "<$relClass href=\"$href.xml\" id=\"{$item->ID}\"></$relClass>\n";
                    }
                }
                $xml .= "</$relName>\n";
            }

            foreach ($obj->manyMany() as $relName => $relClass) {
                $relClass = RestfulServer::parseRelationClass($relClass);

                //remove dot notation from relation names
                $parts = explode('.', $relClass ?? '');
                $relClass = array_shift($parts);
                if (!singleton($relClass)::config()->get('api_access')) {
                    continue;
                }
                // backslashes in FQCNs kills both URIs and XML
                $relClass = $this->sanitiseClassName($relClass);

                // Field filtering
                if ($fields && !in_array($relName, $fields ?? [])) {
                    continue;
                }
                if ($this->customRelations && !in_array($relName, $this->customRelations ?? [])) {
                    continue;
                }

                $xml .= "<$relName linktype=\"many_many\" href=\"$objHref/$relName.xml\">\n";
                $items = $obj->$relName();
                if ($items) {
                    foreach ($items as $item) {
                        $href = Director::absoluteURL($this->config()->api_base . "$relClass/$item->ID");
                        $xml .= "<$relClass href=\"$href.xml\" id=\"{$item->ID}\"></$relClass>\n";
                    }
                }
                $xml .= "</$relName>\n";
            }
        }

        $xml .= "</$className>";

        return $xml;
    }

    /**
     * Generate an XML representation of the given {@link SS_List}.
     *
     * @param SS_List $set
     * @return String XML
     */
    public function convertDataObjectSet(SS_List $set, $fields = null)
    {
        Controller::curr()->getResponse()->addHeader("Content-Type", "text/xml");
        $className = $this->sanitiseClassName(get_class($set));

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= (is_numeric($this->totalSize)) ? "<$className totalSize=\"{$this->totalSize}\">\n" : "<$className>\n";
        foreach ($set as $item) {
            $xml .= $this->convertDataObjectWithoutHeader($item, $fields);
        }
        $xml .= "</$className>";

        return $xml;
    }

    /**
     * @param string $strData
     * @return array|void
     * @throws \Exception
     */
    public function convertStringToArray($strData)
    {
        return self::xml2array($strData);
    }

    /**
     * This was copied from Convert::xml2array() which is deprecated/removed
     *
     * Converts an XML string to a PHP array
     * See http://phpsecurity.readthedocs.org/en/latest/Injection-Attacks.html#xml-external-entity-injection
     *
     * @uses recursiveXMLToArray()
     * @param string $val
     * @param boolean $disableDoctypes Disables the use of DOCTYPE, and will trigger an error if encountered.
     * false by default.
     * @param boolean $disableExternals Does nothing because xml entities are removed
     * @return array
     * @throws Exception
     */
    private static function xml2array($val, $disableDoctypes = false, $disableExternals = false)
    {
        // Check doctype
        if ($disableDoctypes && strpos($val ?? '', '<!DOCTYPE') !== false) {
            throw new InvalidArgumentException('XML Doctype parsing disabled');
        }

        // CVE-2021-41559 Ensure entities are removed due to their inherent security risk via
        // XXE attacks and quadratic blowup attacks, and also lack of consistent support
        $val = preg_replace('/(?s)<!ENTITY.*?>/', '', $val ?? '');

        // If there's still an <!ENTITY> present, then it would be the result of a maliciously
        // crafted XML document e.g. <!ENTITY><!<!ENTITY>ENTITY ext SYSTEM "http://evil.com">
        if (strpos($val ?? '', '<!ENTITY') !== false) {
            throw new InvalidArgumentException('Malicious XML entity detected');
        }

        // This will throw an exception if the XML contains references to any internal entities
        // that were defined in an <!ENTITY /> before it was removed
        $xml = new SimpleXMLElement($val ?? '');
        return self::recursiveXMLToArray($xml);
    }

    /**
     * @param SimpleXMLElement $xml
     *
     * @return mixed
     */
    private static function recursiveXMLToArray($xml)
    {
        $x = null;
        if ($xml instanceof SimpleXMLElement) {
            $attributes = $xml->attributes();
            foreach ($attributes as $k => $v) {
                if ($v) {
                    $a[$k] = (string) $v;
                }
            }
            $x = $xml;
            $xml = get_object_vars($xml);
        }
        if (is_array($xml)) {
            if (count($xml ?? []) === 0) {
                return (string)$x;
            } // for CDATA
            $r = [];
            foreach ($xml as $key => $value) {
                $r[$key] = self::recursiveXMLToArray($value);
            }
            // Attributes
            if (isset($a)) {
                $r['@'] = $a;
            }
            return $r;
        }

        return (string) $xml;
    }
}
