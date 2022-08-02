<?php

namespace SilverStripe\RestfulServer\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Security\Member;

/**
 * An unsecure test authenticator.
 */
class EmailOnlyAuthenticator implements TestOnly
{
    /**
     * @return Member|false
     */
    public static function authenticate()
    {
        //if there is no username or password, fail
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            return null;
        }

        /** @var null|Member $member */
        $member = Member::get()->find('Email', $_SERVER['PHP_AUTH_USER']);

        return $member ?? false;
    }
}
