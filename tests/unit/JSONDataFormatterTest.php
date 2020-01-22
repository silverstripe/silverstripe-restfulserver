<?php

namespace SilverStripe\RestfulServer\Tests;

use SilverStripe\RestfulServer\RestfulServer;
use SilverStripe\ORM\DataObject;
use SilverStripe\RestfulServer\Tests\Stubs\JSONDataFormatterTypeTestObject;
use SilverStripe\RestfulServer\Tests\Stubs\JSONDataFormatterOriginalFunctionality;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\RestfulServer\DataFormatter\JSONDataFormatter;

/**
 * Tests improvements made to JsonTypes,
 * calls method which appends more fields
 */
class JSONDataFormatterTest extends SapphireTest
{
    protected static $fixture_file = 'JSONDataFormatterTest.yml';

    protected static $extra_dataobjects = [
        JSONDataFormatterTypeTestObject::class,
    ];

    protected $usesDatabase = true;

    public function testJSONTypes()
    {

        // Needed as private static $api_access = true; doesn't seem to work on the stub file
        Config::inst()->update(JSONDataFormatterTypeTestObject::class, 'api_access', true);

        // Grab test object
        $formatter = new JSONDataFormatter();
        $parent = $this->objFromFixture(JSONDataFormatterTypeTestObject::class, 'parent');
        $json = json_decode($formatter->convertDataObject($parent));

        // Returns valid array and isn't null
        $this->assertNotEmpty($json, 'Array is empty');

        // Test that original fields still exist, ie id, href, and className
        $standard_id = $json->Children[0]->id;
        $standard_className = $json->Children[0]->className;
        $standard_href = $json->Children[0]->href;

        $this->assertEquals(8, $standard_id, "Standard id field not equal");
        $this->assertEquals('SilverStripe\RestfulServer\Tests\Stubs\JSONDataFormatterTypeTestObject', $standard_className, "Standard className does not equal");
        $this->assertEquals('http://localhost/api/v1/SilverStripe-RestfulServer-Tests-Stubs-JSONDataFormatterTypeTestObject/8.json', $standard_href, "Standard href field not equal");

        // Test method improvements, more fields rather than just id, href, className
        $this->assertEquals(9, $json->ID, "ID not equal");
        $this->assertEquals("SilverStripe\\RestfulServer\\Tests\\Stubs\\JSONDataFormatterTypeTestObject", $json->ClassName, "Class not equal");
        $this->assertEquals(date('Y-m-d H:i:s', time() - 1), $json->LastEdited, "Last edited does not equal");
        $this->assertEquals(date('Y-m-d H:i:s', time() - 1), $json->Created, "Created at does not equal");
        $this->assertEquals("Test Object", $json->Name, "Name not equal");
        $this->assertEquals(false, $json->Active, "Active not equal to false");
        $this->assertEquals(0, $json->Sort, "Sort not equal to 0");
        $this->assertEquals(0, $json->Average, "Average not equal to 0");
        $this->assertEquals(0, $json->ParentID, "ParentID not equal to 0");
    }
}
