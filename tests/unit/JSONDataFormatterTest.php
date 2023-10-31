<?php

namespace SilverStripe\RestfulServer\Tests;

use SilverStripe\RestfulServer\RestfulServer;
use SilverStripe\RestfulServer\Tests\Stubs\JSONDataFormatterTypeTestObject;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\RestfulServer\DataFormatter\JSONDataFormatter;

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
        $this->assertMatchesRegularExpression('/"ID":\d+/', $json, 'PK casted to integer');
        $this->assertMatchesRegularExpression(
            '/"Created":"\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}"/',
            $json,
            'Datetime casted to string'
        );
        $this->assertStringContainsString('"Name":"Parent"', $json, 'String casted to string');
        $this->assertStringContainsString('"Active":true', $json, 'Boolean casted to boolean');
        $this->assertStringContainsString('"Sort":17', $json, 'Integer casted to integer');
        $this->assertStringContainsString('"Average":1.2345', $json, 'Float casted to float');
        $this->assertStringContainsString('"ParentID":0', $json, 'Empty FK is 0');

        $child3 = $this->objFromFixture(JSONDataFormatterTypeTestObject::class, 'child3');
        $json = $formatter->convertDataObject($child3);
        $this->assertStringContainsString('"Name":null', $json, 'Empty string is null');
        $this->assertStringContainsString('"Active":false', $json, 'Empty boolean is false');
        $this->assertStringContainsString('"Sort":0', $json, 'Empty integer is 0');
        $this->assertStringContainsString('"Average":0', $json, 'Empty float is 0');
        $this->assertMatchesRegularExpression('/"ParentID":\d+/', $json, 'FK casted to integer');
    }
}
