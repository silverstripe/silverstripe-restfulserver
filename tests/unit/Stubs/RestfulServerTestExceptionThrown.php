<?php

namespace SilverStripe\RestfulServer\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class RestfulServerTestExceptionThrown
 * @package SilverStripe\RestfulServer\Tests\Stubs
 *
 * @property string Content
 * @property string Title
 */
class RestfulServerTestExceptionThrown extends DataObject implements TestOnly
{
    private static $api_access = true;

    private static $table_name = 'RestfulServerTestExceptionThrown';

    private static $db = array(
        'Content' => 'Text',
        'Title' => 'Text',
    );

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        throw new \Exception('This is an exception test');
    }

    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return true;
    }

    public function canDelete($member = null)
    {
        return true;
    }

    public function canCreate($member = null, $context = array())
    {
        return true;
    }
}
