<?php

namespace SilverStripe\RestfulServer\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class RestfulServerTestPage extends DataObject implements TestOnly
{
    private static $api_access = false;

    private static $table_name = 'RestfulServerTestPage';

    private static $db = array(
        'Title' => 'Text',
        'Content' => 'HTMLText',
    );

    private static $has_one = array(
        'Author' => RestfulServerTestAuthor::class,
    );

    private static $has_many = array(
        'TestComments' => RestfulServerTestComment::class
    );

    private static $belongs_many_many = array(
        'RelatedAuthors' => RestfulServerTestAuthor::class,
    );
}
