<?php

namespace SilverStripe\RestfulServer\Tests;

use SilverStripe\RestfulServer\RestfulServer;
use SilverStripe\ORM\DataObject;
use SilverStripe\RestfulServer\Tests\Stubs\JSONDataFormatterTypeTestObject_1;
use SilverStripe\RestfulServer\Tests\Stubs\JSONDataFormatterTypeTestObject;
use SilverStripe\RestfulServer\Tests\Stubs\JSONDataFormatterTypeTestObject_2;
use SilverStripe\RestfulServer\Tests\Stubs\JSONDataFormatterTypeTestObject_3;

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

    public function testJSONTypes()
    {
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


        // Test convertDataObjectToJSONObject
        $parent = $this->objFromFixture(JSONDataFormatterTypeTestObject::class, 'parent');
        $JsonObject = $formatter->convertDataObjectToJSONObject($parent);
        $JsonObjectArray = json_encode($JsonObject);

        $this->assertEquals('string', gettype($JsonObjectArray)); // is not null
        $this->assertRegexp('/"ID":\d+/', $JsonObjectArray, 'PK casted to integer');
        $this->assertRegexp('/"LastEdited":"\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}"/', $JsonObjectArray, 'Datetime casted to string');
        $this->assertRegexp('/"Created":"\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}"/', $JsonObjectArray, 'Datetime casted to string');

        $this->assertContains('"Name":"Parent"', $JsonObjectArray, 'String casted to string');
        $this->assertContains('"Active":true', $JsonObjectArray, 'Boolean casted to boolean');
        $this->assertContains('"Sort":17', $JsonObjectArray, 'Integer casted to integer');
        $this->assertContains('"Average":1.2345', $JsonObjectArray, 'Float casted to float');
        $this->assertContains('"ParentID":0', $JsonObjectArray, 'Empty FK is 0');

    }

}
