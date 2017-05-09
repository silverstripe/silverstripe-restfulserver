# SilverStripe RestfulServer Module

[![Build Status](https://secure.travis-ci.org/silverstripe/silverstripe-restfulserver.png)](http://travis-ci.org/silverstripe/silverstripe-restfulserver)

## Overview

This class gives your application a RESTful API.  All you have to do is define static $api_access = true on
the appropriate DataObjects.  You will need to ensure that all of your data manipulation and security is defined in
your model layer (ie, the DataObject classes) and not in your Controllers.  This is the recommended design for SilverStripe
applications.

## Requirements

 * SilverStripe 3.0 or newer

## Configuration and Usage

See the documentation in [/docs/en/index.md](docs/en/index.md)
