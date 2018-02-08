<?php

namespace SilverStripe\RestfulServer\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

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
