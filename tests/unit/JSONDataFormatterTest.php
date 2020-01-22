<?php

namespace SilverStripe\RestfulServer\Tests;

use SilverStripe\RestfulServer\RestfulServer;
use SilverStripe\ORM\DataObject;
use SilverStripe\RestfulServer\Tests\Stubs\JSONDataFormatterTypeTestObject;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\RestfulServer\DataFormatter\JSONDataFormatter;

/**
 *
 * @todo Test Relation getters
 * @todo Test filter and limit through GET params
 * @todo Test DELETE verb
 *
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

        $timestamp = date('Y-m-d H:i:s', time() - 1);

        $this->assertEquals(9, $json->ID, "ID not equal");
        $this->assertEquals("SilverStripe\\RestfulServer\\Tests\\Stubs\\JSONDataFormatterTypeTestObject", $json->ClassName, "Class not equal");
        $this->assertEquals($timestamp, $json->LastEdited, "Last edited does not equal");
        $this->assertEquals($timestamp, $json->Created, "Created at does not equal");
        $this->assertEquals("Test Object", $json->Name);
        $this->assertEquals(false, $json->Active);
        $this->assertEquals(0, $json->Sort);
        $this->assertEquals(0, $json->Average);
        $this->assertEquals(0, $json->ParentID);
    }
}
