<?php

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
        //if there is no username or password, break
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            return false;
        }

        //Attempt to authenticate with the default authenticator for the site
        $authClass = Authenticator::get_default_authenticator();
        $member = $authClass::authenticate(array(
            'Email' => $_SERVER['PHP_AUTH_USER'],
            'Password' => $_SERVER['PHP_AUTH_PW'],
        ));

        //Log the member in and return the member, if they were found
        if ($member) {
            $member->LogIn(false);
            return $member;
        }
        return false;
    }
}
