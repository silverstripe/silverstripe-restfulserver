<?php

namespace SilverStripe\RestfulServer\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class RestfulServerTestValidationFailure
 * @package SilverStripe\RestfulServer\Tests\Stubs
 *
 * @property string Content
 * @property string Title
 */
class RestfulServerTestValidationFailure extends DataObject implements TestOnly
{
    private static $api_access = true;

    private static $table_name = 'RestfulServerTestValidationFailure';

    private static $db = array(
        'Content' => 'Text',
        'Title' => 'Text',
    );

    /**
     * @return \SilverStripe\ORM\ValidationResult
     */
    public function validate()
    {
        $result = parent::validate();

        if (strlen($this->Content) === 0) {
            $result->addFieldError('Content', 'Content required');
        }

        if (strlen($this->Title) === 0) {
            $result->addFieldError('Title', 'Title required');
        }

        return $result;
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
