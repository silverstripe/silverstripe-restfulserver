# SilverStripe RestfulServer Module

[![Build Status](https://secure.travis-ci.org/silverstripe/silverstripe-restfulserver.png)](http://travis-ci.org/silverstripe/silverstripe-restfulserver)

## Overview

This class gives your application a RESTful API.  All you have to do is define static $api_access = true on
the appropriate DataObjects.  You will need to ensure that all of your data manipulation and security is defined in
your model layer (ie, the DataObject classes) and not in your Controllers.  This is the recommended design for SilverStripe
applications.

## Requirements

 * SilverStripe 3.0 or newer

## Configuration

Enabling restful access on a model will also enable a SOAP API, see `SOAPModelAccess`.

Example DataObject with simple api access, giving full access to all object properties and relations,
unless explicitly controlled through model permissions.

	class Article extends DataObject {
		static $db = array('Title'=>'Text','Published'=>'Boolean');
		static $api_access = true;
	}

Example DataObject with advanced api access, limiting viewing and editing to Title attribute only:

	class Article extends DataObject {
		static $db = array('Title'=>'Text','Published'=>'Boolean');
		static $api_access = array(
			'view' => array('Title'),
			'edit' => array('Title'),
		);
	}

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

Access control is implemented through the usual Member system with Basicauth authentication only.
By default, you have to bear the ADMIN permission to retrieve or send any data.
You should override the following built-in methods to customize permission control on a
class- and object-level:

- `DataObject::canView()`
- `DataObject::canEdit()`
- `DataObject::canDelete()`
- `DataObject::canCreate()`

See `DataObject` documentation for further details.

You can specify the character-encoding for any input on the HTTP Content-Type.
At the moment, only UTF-8 is supported. All output is made in UTF-8 regardless of Accept headers.