<?php

namespace SilverStripe\RestfulServer\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

class RestfulServerTestSecretThing extends DataObject implements TestOnly, PermissionProvider
{
    private static $api_access = true;

    private static $table_name = 'RestfulServerTestSecretThing';

    private static $db = array(
        "Name" => "Varchar(255)",
    );

    public function canView($member = null)
    {
        return Permission::checkMember($member, 'VIEW_SecretThing');
    }

    public function providePermissions()
    {
        return array(
            'VIEW_SecretThing' => 'View Secret Things',
        );
    }
}
