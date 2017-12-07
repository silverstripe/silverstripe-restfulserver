<?php

namespace SilverStripe\RestfulServer;

use SilverStripe\Security\Authenticator;
use SilverStripe\Control\Controller;
use SilverStripe\Security\Security;

/**
 * A simple authenticator for the Restful server.
 *
 * This allows users to be authenticated against that RestfulServer using their
 * login details, however they will be passed 'in the open' and will require the
 * application accessing the RestfulServer to store logins in plain text (or in
 * decrytable form)
 */
class BasicRestfulAuthenticator
{
    /**
     * The authenticate function
     *
     * Takes the basic auth details and attempts to log a user in from the DB
     *
     * @return Member|false The Member object, or false if no member
     */
    public static function authenticate()
    {
        //if there is no username or password, fail
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            return null;
        }

        // With a valid user and password, check the password is correct
        $data = [
            'Email' => $_SERVER['PHP_AUTH_USER'],
            'Password' => $_SERVER['PHP_AUTH_PW'],
        ];
        $request = Controller::curr()->getRequest();
        $authenticators = Security::singleton()->getApplicableAuthenticators(Authenticator::LOGIN);
        $member = null;
        foreach ($authenticators as $authenticator) {
            $member = $authenticator->authenticate($data, $request);
            if ($member) {
                break;
            }
        }
        return $member;
    }
}
