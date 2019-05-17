# SilverStripe RestfulServer Module

[![Build Status](https://travis-ci.org/silverstripe/silverstripe-restfulserver.svg?branch=master)](https://travis-ci.org/silverstripe/silverstripe-restfulserver)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverstripe/silverstripe-restfulserver/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-restfulserver/?branch=master)
[![codecov](https://codecov.io/gh/silverstripe/silverstripe-restfulserver/branch/master/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-restfulserver)

## Overview

This class gives your application a RESTful API.  All you have to do is set the `api_access` configuration option to `true`
on the appropriate `DataObject`.  You will need to ensure that all of your data manipulation and security is defined in
your model layer (ie, the `DataObject` classes) and not in your Controllers.  This is the recommended design for SilverStripe
applications.

## Requirements

* SilverStripe 4.0 or higher

For a SilverStripe 3.x compatible version of this module, please see the [1.0 branch, or 1.x release line](https://github.com/silverstripe/silverstripe-restfulserver/tree/1.0#readme).

## Configuration

Example `DataObject` with simple API access, giving full access to all object properties and relations,
unless explicitly controlled through model permissions.

```php
namespace Vendor\Project;

use SilverStripe\ORM\DataObject;

class Article extends DataObject {

	private static $db = [
        'Title'=>'Text',
        'Published'=>'Boolean'
    ];

	private static $api_access = true;
}
```

Example `DataObject` with advanced API access, limiting viewing and editing to the "Title" attribute only:

```php
namespace Vendor\Project;

use SilverStripe\ORM\DataObject;

class Article extends DataObject {

    private static $db = [
        'Title'=>'Text',
        'Published'=>'Boolean'
    ];

    private static $api_access = [
        'view' => ['Title'],
        'edit' => ['Title']
    ];
}
```

Example `DataObject` field mapping, allows aliasing fields so that public requests and responses display different field names:

```php
namespace Vendor\Project;

use SilverStripe\ORM\DataObject;

class Article extends DataObject {

    private static $db = [
        'Title'=>'Text',
        'Published'=>'Boolean'
    ];

    private static $api_access = [
        'view' => ['Title', 'Content'],
    ];

    private static $api_field_mapping = [
        'customTitle' => 'Title',
    ];
}
```

Example `DataObject` `HasMany` and `ManyMany` field-display handling. Only available on `JSONDataFormatter`. Declaring a `getApiFields` method in your `DataObject` (or an `Extension` subclass) allows additional fields to be shown on those relations, in addition to "id", "className" and "href":

```php
namespace Vendor\Project;

use SilverStripe\ORM\DataObject;

class Article extends DataObject {

    private static $db = [
        'Title'=>'Text',
        'Published'=>'Boolean'
    ];

    private static $api_access = true;

    /**
     * @param  array $baseFields
     * @return array
     */
    public function getApiFields($baseFields)
    {
        return [
            'Title' => $this->Title,
        ];
    }
}
```

Example `DataObject` `HasMany` and `ManyMany` field-display handling. Only available on `JSONDataFormatter`. Declaring a `getApiFields` method in your `DataObject` (or an `Extension` subclass) allows existing fields that the formatter returns (like "id", "className" and "href"), to be overloaded:

```php
namespace Vendor\Project;

use SilverStripe\ORM\DataObject;

class Article extends DataObject {

    private static $db = [
        'Title'=>'Text',
        'Published'=>'Boolean'
    ];

    private static $api_access = true;

    /**
     * @param  array $baseFields
     * @return array
     */
    public function getApiFields($baseFields)
    {
        return [
            'href' => $this->myHrefOverrideMethod($baseFields['href']),
        ];
    }
}
```

Given a `DataObject` with values:
```yml
    ID: 12
    Title: Title Value
    Content: Content value
```
which when requesting with the url `/api/v1/Vendor-Project-Article/12?fields=customTitle,Content` and `Accept: application/json` the response will look like:
```Javascript
{
    "customTitle": "Title Value",
    "Content": "Content value"
}
```
Similarly, `PUT` or `POST` requests will have fields transformed from the alias name to the DB field name.

## Supported operations

 - `GET /api/v1/(ClassName)/(ID)` - gets a database record
 - `GET /api/v1/(ClassName)/(ID)/(Relation)` - get all of the records linked to this database record by the given reatlion
 - `GET /api/v1/(ClassName)?(Field)=(Val)&(Field)=(Val)` - searches for matching database records
 - `POST /api/v1/(ClassName)` - create a new database record
 - `PUT /api/v1/(ClassName)/(ID)` - updates a database record
 - `PUT /api/v1/(ClassName)/(ID)/(Relation)` - updates a relation, replacing the existing record(s) (NOT IMPLEMENTED YET)
 - `POST /api/v1/(ClassName)/(ID)/(Relation)` - updates a relation, appending to the existing record(s) (NOT IMPLEMENTED YET)

 - DELETE /api/v1/(ClassName)/(ID) - deletes a database record (NOT IMPLEMENTED YET)
 - DELETE /api/v1/(ClassName)/(ID)/(Relation)/(ForeignID) - remove the relationship between two database records, but don't actually delete the foreign object (NOT IMPLEMENTED YET)
 - POST /api/v1/(ClassName)/(ID)/(MethodName) - executes a method on the given object (e.g, publish)

## Search

You can trigger searches based on the fields specified on `DataObject::searchable_fields` and passed
through `DataObject::getDefaultSearchContext()`. Just add a key-value pair with the search-term
to the url, e.g. /api/v1/(ClassName)/?Title=mytitle.

## Other url-modifiers

- `&limit=<numeric>`: Limit the result set
- `&relationdepth=<numeric>`: Displays links to existing has-one and has-many relationships to a certain depth (Default: 1)
- `&fields=<string>`: Comma-separated list of fields on the output object (defaults to all database-columns).
  Handy to limit output for bandwidth and performance reasons.
- `&sort=<myfield>&dir=<asc|desc>`
- `&add_fields=<string>`: Comma-separated list of additional fields, for example dynamic getters.

## Access control

Access control is implemented through the usual Member system with BasicAuth authentication only.
By default, you have to bear the ADMIN permission to retrieve or send any data.
You should override the following built-in methods to customize permission control on a
class- and object-level:

- `DataObject::canView()`
- `DataObject::canEdit()`
- `DataObject::canDelete()`
- `DataObject::canCreate()`

See `SilverStripe\ORM\DataObject` documentation for further details.

You can specify the character-encoding for any input on the HTTP Content-Type.
At the moment, only UTF-8 is supported. All output is made in UTF-8 regardless of Accept headers.
