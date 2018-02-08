<?php

namespace SilverStripe\RestfulServer\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Everybody can view comments, logged in members in the "users" group can create comments,
 * but only "editors" can edit or delete them.
 *
 */
class RestfulServerTestComment extends DataObject implements PermissionProvider, TestOnly
{
    private static $api_access = true;

    private static $table_name = 'RestfulServerTestComment';

    private static $db = array(
        "Name" => "Varchar(255)",
        "Comment" => "Text"
    );

    private static $has_one = array(
        'Page' => RestfulServerTestPage::class,
        'Author' => RestfulServerTestAuthor::class,
    );

    public function providePermissions()
    {
        return array(
            'EDIT_Comment' => 'Edit Comment Objects',
            'CREATE_Comment' => 'Create Comment Objects',
            'DELETE_Comment' => 'Delete Comment Objects',
        );
    }

    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'EDIT_Comment');
    }

    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'DELETE_Comment');
    }

    public function canCreate($member = null, $context = array())
    {
        return Permission::checkMember($member, 'CREATE_Comment');
    }
}
