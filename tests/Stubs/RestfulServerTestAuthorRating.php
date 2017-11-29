<?php

namespace SilverStripe\RestfulServer\Tests\Stubs;

use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestAuthor;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

class RestfulServerTestAuthorRating extends DataObject implements TestOnly
{
    private static $api_access = array(
        'view' => array(
            'Rating',
            'WriteProtectedField',
            'Author'
        ),
        'edit' => array(
            'Rating'
        )
    );

    private static $table_name = 'RestfulServerTestAuthorRating';

    private static $db = array(
        'Rating' => 'Int',
        'SecretField' => 'Text',
        'WriteProtectedField' => 'Text',
    );

    private static $has_one = array(
        'Author' => RestfulServerTestAuthor::class,
        'SecretRelation' => RestfulServerTestAuthor::class,
    );

    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return true;
    }

    public function canCreate($member = null, $context = array())
    {
        return true;
    }
}
