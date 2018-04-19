<?php

namespace SilverStripe\RestfulServer\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class JSONDataFormatterTypeTestObject extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'JSONDataFormatterTypeTestObject';

    /**
     * @var array
     */
    private static $db = [
        'Name' => 'Varchar',
        'Active' => 'Boolean',
        'Sort' => 'Int',
        'Average' => 'Float',
    ];

    private static $has_one = [
        'Parent' => JSONDataFormatterTypeTestObject::class,
    ];

    private static $has_many = [
        'Children' => JSONDataFormatterTypeTestObject::class,
    ];
}
