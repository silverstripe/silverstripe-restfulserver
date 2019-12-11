<?php

namespace SilverStripe\RestfulServer\Tests;

use SilverStripe\RestfulServer\RestfulServer;
use SilverStripe\ORM\DataObject;
use SilverStripe\RestfulServer\Tests\Stubs\JSONDataFormatterTypeTestObject;

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
        $this->assertContains('"Active":true', $json, 'boolean is false');
        $this->assertContains('"Sort":17', $json, 'Empty integer is 0');
        $this->assertContains('"Average":1.2345', $json, 'Empty float is 0');
    }

}
