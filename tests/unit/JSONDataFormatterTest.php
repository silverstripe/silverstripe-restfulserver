<?php

namespace SilverStripe\RestfulServer\Tests;

use SilverStripe\RestfulServer\RestfulServer;
use SilverStripe\ORM\DataObject;
use SilverStripe\RestfulServer\Tests\Stubs\JSONDataFormatterTypeTestObject;
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
        $json = $formatter->convertDataObject($parent);

        $this->assertRegexp('/"ID":\d+/', $json, 'PK casted to integer');
        $this->assertRegexp('/"Created":"\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}"/', $json, 'Datetime casted to string');
        $this->assertContains('"Name":"Parent"', $json, 'String casted to string');
        $this->assertContains('"Active":true', $json, 'Boolean casted to boolean');
        $this->assertContains('"Sort":17', $json, 'Integer casted to integer');
        $this->assertContains('"Average":1.2345', $json, 'Float casted to float');
        $this->assertContains('"ParentID":0', $json, 'Empty FK is 0');

        $child3 = $this->objFromFixture(JSONDataFormatterTypeTestObject::class, 'child3');
        $json = $formatter->convertDataObject($child3);

        $this->assertContains('"Name":null', $json, 'Empty string is null');
        $this->assertContains('"Active":false', $json, 'Empty boolean is false');
        $this->assertContains('"Sort":0', $json, 'Empty integer is 0');
        $this->assertContains('"Average":0', $json, 'Empty float is 0');
        $this->assertRegexp('/"ParentID":\d+/', $json, 'FK casted to integer');

        $original = $this->objFromFixture(JSONDataFormatterTypeTestObject::class, 'original');
        $json = json_decode($formatter->convertDataObject($original));

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
        $this->assertEquals("Test Object", $json->Name, "Name not equal");
        $this->assertEquals(false, $json->Active, "Active not equal to false");
        $this->assertEquals(0, $json->Sort, "Sort not equal to 0");
        $this->assertEquals(0, $json->Average, "Average not equal to 0");
        $this->assertEquals(0, $json->ParentID, "ParentID not equal to 0");
    }
}
