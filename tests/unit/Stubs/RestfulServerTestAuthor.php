<?php

namespace SilverStripe\RestfulServer\Tests\Stubs;

use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestPage;
use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestAuthor;
use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestAuthorRating;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

class RestfulServerTestAuthor extends DataObject implements TestOnly
{
    private static $api_access = true;

    private static $table_name = 'RestfulServerTestAuthor';

    private static $db = array(
        'Name' => 'Text',
    );

    private static $many_many = array(
        'RelatedPages' => RestfulServerTestPage::class,
        'RelatedAuthors' => RestfulServerTestAuthor::class,
    );

    private static $has_many = array(
        'PublishedPages' => RestfulServerTestPage::class,
        'Ratings' => RestfulServerTestAuthorRating::class,
    );

    public function canView($member = null)
    {
        return true;
    }
}
