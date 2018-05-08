<?php

namespace SilverStripe\RestfulServer\Tests;

use SilverStripe\RestfulServer\RestfulServer;
use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestComment;
use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestExceptionThrown;
use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestSecretThing;
use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestPage;
use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestAuthor;
use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestAuthorRating;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Control\Controller;
use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestValidationFailure;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\RestfulServer\DataFormatter\JSONDataFormatter;
use Page;
use SilverStripe\Core\Config\Config;

/**
 *
 * @todo Test Relation getters
 * @todo Test filter and limit through GET params
 * @todo Test DELETE verb
 *
 */
class RestfulServerTest extends SapphireTest
{
    protected static $fixture_file = 'RestfulServerTest.yml';

    protected $baseURI = 'http://www.fakesite.test';

    protected static $extra_dataobjects = [
        RestfulServerTestComment::class,
        RestfulServerTestSecretThing::class,
        RestfulServerTestPage::class,
        RestfulServerTestAuthor::class,
        RestfulServerTestAuthorRating::class,
        RestfulServerTestValidationFailure::class,
        RestfulServerTestExceptionThrown::class,
    ];

    protected function urlSafeClassname($classname)
    {
        return str_replace('\\', '-', $classname);
    }

    protected function setUp()
    {
        parent::setUp();
        Director::config()->set('alternate_base_url', $this->baseURI);
        Security::setCurrentUser(null);
    }

    public function testApiAccess()
    {
        $comment1 = $this->objFromFixture(RestfulServerTestComment::class, 'comment1');
        $page1 = $this->objFromFixture(RestfulServerTestPage::class, 'page1');

        // normal GET should succeed with $api_access enabled
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $comment1->ID;

        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(200, $response->getStatusCode());

        $_SERVER['PHP_AUTH_USER'] = 'user@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'user';

        // even with logged in user a GET with $api_access disabled should fail
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestPage::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $page1->ID;
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(401, $response->getStatusCode());

        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
    }

    public function testApiAccessBoolean()
    {
        $comment1 = $this->objFromFixture(RestfulServerTestComment::class, 'comment1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $comment1->ID;
        $response = Director::test($url, null, null, 'GET');
        $this->assertContains('<ID>', $response->getBody());
        $this->assertContains('<Name>', $response->getBody());
        $this->assertContains('<Comment>', $response->getBody());
        $this->assertContains('<Page', $response->getBody());
        $this->assertContains('<Author', $response->getBody());
    }

    public function testAuthenticatedGET()
    {
        $thing1 = $this->objFromFixture(RestfulServerTestSecretThing::class, 'thing1');
        $comment1 = $this->objFromFixture(RestfulServerTestComment::class, 'comment1');

        // @todo create additional mock object with authenticated VIEW permissions
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestSecretThing::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $thing1->ID;
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(401, $response->getStatusCode());

        $_SERVER['PHP_AUTH_USER'] = 'user@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'user';

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $comment1->ID;
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(200, $response->getStatusCode());

        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
    }

    public function testGETWithFieldAlias()
    {
        Config::inst()->set(RestfulServerTestAuthorRating::class, 'api_field_mapping', ['rate' => 'Rating']);
        $rating1 = $this->objFromFixture(RestfulServerTestAuthorRating::class, 'rating1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $rating1->ID;
        $response = Director::test($url, null, null, 'GET');
        $responseArr = Convert::xml2array($response->getBody());
        $this->assertEquals(3, $responseArr['rate']);
    }

    public function testAuthenticatedPUT()
    {
        $comment1 = $this->objFromFixture(RestfulServerTestComment::class, 'comment1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $comment1->ID;
        $data = array('Comment' => 'created');

        $response = Director::test($url, $data, null, 'PUT');
        $this->assertEquals(401, $response->getStatusCode()); // Permission failure

        $_SERVER['PHP_AUTH_USER'] = 'editor@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'editor';
        $response = Director::test($url, $data, null, 'PUT');
        $this->assertEquals(202, $response->getStatusCode()); // Accepted

        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
    }

    public function testGETRelationshipsXML()
    {
        $author1 = $this->objFromFixture(RestfulServerTestAuthor::class, 'author1');
        $rating1 = $this->objFromFixture(RestfulServerTestAuthorRating::class, 'rating1');
        $rating2 = $this->objFromFixture(RestfulServerTestAuthorRating::class, 'rating2');

        // @todo should be set up by fixtures, doesn't work for some reason...
        $author1->Ratings()->add($rating1);
        $author1->Ratings()->add($rating2);

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthor::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $author1->ID;
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(200, $response->getStatusCode());

        $responseArr = Convert::xml2array($response->getBody());
        $xmlTagSafeClassName = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $ratingsArr = $responseArr['Ratings'][$xmlTagSafeClassName];
        $this->assertEquals(2, count($ratingsArr));
        $ratingIDs = array(
            (int)$ratingsArr[0]['@attributes']['id'],
            (int)$ratingsArr[1]['@attributes']['id']
        );
        $this->assertContains($rating1->ID, $ratingIDs);
        $this->assertContains($rating2->ID, $ratingIDs);
    }

    public function testGETRelationshipsWithAlias()
    {
        // Alias do not currently work with Relationships
        Config::inst()->set(RestfulServerTestAuthor::class, 'api_field_mapping', ['stars' => 'Ratings']);
        $author1 = $this->objFromFixture(RestfulServerTestAuthor::class, 'author1');
        $rating1 = $this->objFromFixture(RestfulServerTestAuthorRating::class, 'rating1');

        // @todo should be set up by fixtures, doesn't work for some reason...
        $author1->Ratings()->add($rating1);

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthor::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $author1->ID . '?add_fields=stars';
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(200, $response->getStatusCode());

        $responseArr = Convert::xml2array($response->getBody());
        $xmlTagSafeClassName = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);

        $this->assertTrue(array_key_exists('Ratings', $responseArr));
        $this->assertFalse(array_key_exists('stars', $responseArr));
    }

    public function testGETManyManyRelationshipsXML()
    {
        // author4 has related authors author2 and author3
        $author2 = $this->objFromFixture(RestfulServerTestAuthor::class, 'author2');
        $author3 = $this->objFromFixture(RestfulServerTestAuthor::class, 'author3');
        $author4 = $this->objFromFixture(RestfulServerTestAuthor::class, 'author4');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthor::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $author4->ID . '/RelatedAuthors';
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(200, $response->getStatusCode());
        $arr = Convert::xml2array($response->getBody());
        $xmlSafeClassName = $this->urlSafeClassname(RestfulServerTestAuthor::class);
        $authorsArr = $arr[$xmlSafeClassName];

        $this->assertEquals(2, count($authorsArr));
        $ratingIDs = array(
            (int)$authorsArr[0]['ID'],
            (int)$authorsArr[1]['ID']
        );
        $this->assertContains($author2->ID, $ratingIDs);
        $this->assertContains($author3->ID, $ratingIDs);
    }

    public function testPUTWithFormEncoded()
    {
        $comment1 = $this->objFromFixture(RestfulServerTestComment::class, 'comment1');

        $_SERVER['PHP_AUTH_USER'] = 'editor@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'editor';

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $comment1->ID;
        $body = 'Name=Updated Comment&Comment=updated';
        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        );
        $response = Director::test($url, null, null, 'PUT', $body, $headers);
        $this->assertEquals(202, $response->getStatusCode()); // Accepted
        // Assumption: XML is default output
        $responseArr = Convert::xml2array($response->getBody());
        $this->assertEquals($comment1->ID, $responseArr['ID']);
        $this->assertEquals('updated', $responseArr['Comment']);
        $this->assertEquals('Updated Comment', $responseArr['Name']);

        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
    }

    public function testPOSTWithFormEncoded()
    {
        $comment1 = $this->objFromFixture(RestfulServerTestComment::class, 'comment1');

        $_SERVER['PHP_AUTH_USER'] = 'editor@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'editor';

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname";
        $body = 'Name=New Comment&Comment=created';
        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        );
        $response = Director::test($url, null, null, 'POST', $body, $headers);
        $this->assertEquals(201, $response->getStatusCode()); // Created
        // Assumption: XML is default output
        $responseArr = Convert::xml2array($response->getBody());
        $this->assertTrue($responseArr['ID'] > 0);
        $this->assertNotEquals($responseArr['ID'], $comment1->ID);
        $this->assertEquals('created', $responseArr['Comment']);
        $this->assertEquals('New Comment', $responseArr['Name']);
        $this->assertEquals(
            Controller::join_links($url, $responseArr['ID'] . '.xml'),
            $response->getHeader('Location')
        );

        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
    }

    public function testPostWithoutBodyReturnsNoContent()
    {
        $_SERVER['PHP_AUTH_USER'] = 'editor@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'editor';

        $url = "{$this->baseURI}/api/v1/" . RestfulServerTestComment::class;
        $response = Director::test($url, null, null, 'POST');

        $this->assertEquals('No Content', $response->getBody());

        unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
    }

    public function testPUTwithJSON()
    {
        $comment1 = $this->objFromFixture(RestfulServerTestComment::class, 'comment1');

        $_SERVER['PHP_AUTH_USER'] = 'editor@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'editor';

        // by acceptance mimetype
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $comment1->ID;
        $body = '{"Comment":"updated"}';
        $response = Director::test($url, null, null, 'PUT', $body, array(
            'Content-Type'=>'application/json',
            'Accept' => 'application/json'
        ));
        $this->assertEquals(202, $response->getStatusCode()); // Accepted
        $obj = Convert::json2obj($response->getBody());
        $this->assertEquals($comment1->ID, $obj->ID);
        $this->assertEquals('updated', $obj->Comment);

        // by extension
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/{$comment1->ID}.json";
        $body = '{"Comment":"updated"}';
        $response = Director::test($url, null, null, 'PUT', $body);
        $this->assertEquals(202, $response->getStatusCode()); // Accepted
        $this->assertEquals($url, $response->getHeader('Location'));
        $obj = Convert::json2obj($response->getBody());
        $this->assertEquals($comment1->ID, $obj->ID);
        $this->assertEquals('updated', $obj->Comment);

        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
    }

    public function testPUTwithXML()
    {
        $comment1 = $this->objFromFixture(RestfulServerTestComment::class, 'comment1');

        $_SERVER['PHP_AUTH_USER'] = 'editor@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'editor';

        // by mimetype
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $comment1->ID;
        $body = '<RestfulServerTestComment><Comment>updated</Comment></RestfulServerTestComment>';
        $response = Director::test($url, null, null, 'PUT', $body, array('Content-Type'=>'text/xml'));
        $this->assertEquals(202, $response->getStatusCode()); // Accepted
        $obj = Convert::xml2array($response->getBody());
        $this->assertEquals($comment1->ID, $obj['ID']);
        $this->assertEquals('updated', $obj['Comment']);

        // by extension
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/{$comment1->ID}.xml";
        $body = '<RestfulServerTestComment><Comment>updated</Comment></RestfulServerTestComment>';
        $response = Director::test($url, null, null, 'PUT', $body);
        $this->assertEquals(202, $response->getStatusCode()); // Accepted
        $this->assertEquals($url, $response->getHeader('Location'));
        $obj = Convert::xml2array($response->getBody());
        $this->assertEquals($comment1->ID, $obj['ID']);
        $this->assertEquals('updated', $obj['Comment']);

        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
    }

    public function testHTTPAcceptAndContentType()
    {
        $comment1 = $this->objFromFixture(RestfulServerTestComment::class, 'comment1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $comment1->ID;

        $headers = array('Accept' => 'application/json');
        $response = Director::test($url, null, null, 'GET', null, $headers);
        $this->assertEquals(200, $response->getStatusCode()); // Success
        $obj = Convert::json2obj($response->getBody());
        $this->assertEquals($comment1->ID, $obj->ID);
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
    }

    public function testNotFound()
    {
        $_SERVER['PHP_AUTH_USER'] = 'user@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'user';

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/99";
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(404, $response->getStatusCode());

        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
    }

    public function testMethodNotAllowed()
    {
        $comment1 = $this->objFromFixture(RestfulServerTestComment::class, 'comment1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $comment1->ID;
        $response = Director::test($url, null, null, 'UNKNOWNHTTPMETHOD');
        $this->assertEquals(405, $response->getStatusCode());
    }

    public function testConflictOnExistingResourceWhenUsingPost()
    {
        $rating1 = $this->objFromFixture(RestfulServerTestAuthorRating::class, 'rating1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $rating1->ID;
        $response = Director::test($url, null, null, 'POST');
        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testUnsupportedMediaType()
    {
        $_SERVER['PHP_AUTH_USER'] = 'user@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'user';

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname";
        $data = "Comment||\/||updated"; // weird format
        $headers = array('Content-Type' => 'text/weirdformat');
        $response = Director::test($url, null, null, 'POST', $data, $headers);
        $this->assertEquals(415, $response->getStatusCode());

        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
    }

    public function testXMLValueFormatting()
    {
        $rating1 = $this->objFromFixture(RestfulServerTestAuthorRating::class, 'rating1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $rating1->ID;
        $response = Director::test($url, null, null, 'GET');
        $this->assertContains('<ID>' . $rating1->ID . '</ID>', $response->getBody());
        $this->assertContains('<Rating>' . $rating1->Rating . '</Rating>', $response->getBody());
    }

    public function testXMLValueFormattingWithFieldAlias()
    {
        Config::inst()->set(RestfulServerTestAuthorRating::class, 'api_field_mapping', ['rate' => 'Rating']);
        $rating1 = $this->objFromFixture(RestfulServerTestAuthorRating::class, 'rating1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $rating1->ID;
        $response = Director::test($url, null, null, 'GET');
        $this->assertContains('<rate>' . $rating1->Rating . '</rate>', $response->getBody());
    }

    public function testApiAccessFieldRestrictions()
    {
        $author1 = $this->objFromFixture(RestfulServerTestAuthor::class, 'author1');
        $rating1 = $this->objFromFixture(RestfulServerTestAuthorRating::class, 'rating1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $rating1->ID;
        $response = Director::test($url, null, null, 'GET');
        $this->assertContains('<ID>', $response->getBody());
        $this->assertContains('<Rating>', $response->getBody());
        $this->assertContains('<Author', $response->getBody());
        $this->assertNotContains('<SecretField>', $response->getBody());
        $this->assertNotContains('<SecretRelation>', $response->getBody());

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $rating1->ID . '?add_fields=SecretField,SecretRelation';
        $response = Director::test($url, null, null, 'GET');
        $this->assertNotContains(
            '<SecretField>',
            $response->getBody(),
            '"add_fields" URL parameter filters out disallowed fields from $api_access'
        );
        $this->assertNotContains(
            '<SecretRelation>',
            $response->getBody(),
            '"add_fields" URL parameter filters out disallowed relations from $api_access'
        );

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $rating1->ID . '?fields=SecretField,SecretRelation';
        $response = Director::test($url, null, null, 'GET');
        $this->assertNotContains(
            '<SecretField>',
            $response->getBody(),
            '"fields" URL parameter filters out disallowed fields from $api_access'
        );
        $this->assertNotContains(
            '<SecretRelation>',
            $response->getBody(),
            '"fields" URL parameter filters out disallowed relations from $api_access'
        );

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthor::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $author1->ID . '/Ratings';
        $response = Director::test($url, null, null, 'GET');
        $this->assertContains(
            '<Rating>',
            $response->getBody(),
            'Relation viewer shows fields allowed through $api_access'
        );
        $this->assertNotContains(
            '<SecretField>',
            $response->getBody(),
            'Relation viewer on has-many filters out disallowed fields from $api_access'
        );
    }

    public function testApiAccessRelationRestrictionsInline()
    {
        $author1 = $this->objFromFixture(RestfulServerTestAuthor::class, 'author1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthor::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $author1->ID;
        $response = Director::test($url, null, null, 'GET');
        $this->assertNotContains('<RelatedPages', $response->getBody(), 'Restricts many-many with api_access=false');
        $this->assertNotContains('<PublishedPages', $response->getBody(), 'Restricts has-many with api_access=false');
    }

    public function testApiAccessRelationRestrictionsOnEndpoint()
    {
        $author1 = $this->objFromFixture(RestfulServerTestAuthor::class, 'author1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthor::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $author1->ID . "/ProfilePage";
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(404, $response->getStatusCode(), 'Restricts has-one with api_access=false');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthor::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $author1->ID . "/RelatedPages";
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(404, $response->getStatusCode(), 'Restricts many-many with api_access=false');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthor::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $author1->ID . "/PublishedPages";
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(404, $response->getStatusCode(), 'Restricts has-many with api_access=false');
    }

    public function testApiAccessWithPUT()
    {
        $rating1 = $this->objFromFixture(RestfulServerTestAuthorRating::class, 'rating1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $rating1->ID;
        $data = array(
            'Rating' => '42',
            'WriteProtectedField' => 'haxx0red'
        );
        $response = Director::test($url, $data, null, 'PUT');
        // Assumption: XML is default output
        $responseArr = Convert::xml2array($response->getBody());
        $this->assertEquals(42, $responseArr['Rating']);
        $this->assertNotEquals('haxx0red', $responseArr['WriteProtectedField']);
    }

    public function testFieldAliasWithPUT()
    {
        Config::inst()->set(RestfulServerTestAuthorRating::class, 'api_field_mapping', ['rate' => 'Rating']);
        $rating1 = $this->objFromFixture(RestfulServerTestAuthorRating::class, 'rating1');
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/" . $rating1->ID;
        // Test input with original fieldname
        $data = array(
            'Rating' => '42',
        );
        $response = Director::test($url, $data, null, 'PUT');
        // Assumption: XML is default output
        $responseArr = Convert::xml2array($response->getBody());
        // should output with aliased name
        $this->assertEquals(42, $responseArr['rate']);
    }

    public function testJSONDataFormatter()
    {
        $formatter = new JSONDataFormatter();
        $editor = $this->objFromFixture(Member::class, 'editor');
        $user = $this->objFromFixture(Member::class, 'user');

        // The DataFormatter performs canView calls
        // these are `Member`s so we need to be ADMIN types
        $this->logInWithPermission('ADMIN');

        $this->assertEquals(
            '{"FirstName":"Editor","Email":"editor@test.com"}',
            $formatter->convertDataObject($editor, ["FirstName", "Email"]),
            "Correct JSON formatting with field subset"
        );

        $set = Member::get()
            ->filter('ID', [$editor->ID, $user->ID])
            ->sort('"Email" ASC'); // for sorting for postgres
        $this->assertEquals(
            '{"totalSize":null,"items":[{"FirstName":"Editor","Email":"editor@test.com"},' .
                '{"FirstName":"User","Email":"user@test.com"}]}',
            $formatter->convertDataObjectSet($set, ["FirstName", "Email"]),
            "Correct JSON formatting on a dataobjectset with field filter"
        );
    }

    public function testJSONDataFormatterWithFieldAlias()
    {
        Config::inst()->set(Member::class, 'api_field_mapping', ['MyName' => 'FirstName']);
        $formatter = new JSONDataFormatter();
        $editor = $this->objFromFixture(Member::class, 'editor');
        $user = $this->objFromFixture(Member::class, 'user');

        // The DataFormatter performs canView calls
        // these are `Member`s so we need to be ADMIN types
        $this->logInWithPermission('ADMIN');

        $set = Member::get()
            ->filter('ID', [$editor->ID, $user->ID])
            ->sort('"Email" ASC'); // for sorting for postgres

        $this->assertEquals(
            '{"totalSize":null,"items":[{"MyName":"Editor","Email":"editor@test.com"},' .
                '{"MyName":"User","Email":"user@test.com"}]}',
            $formatter->convertDataObjectSet($set, ["FirstName", "Email"]),
            "Correct JSON formatting with field alias"
        );
    }

    public function testApiAccessWithPOST()
    {
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/";
        $data = [
            'Rating' => '42',
            'WriteProtectedField' => 'haxx0red'
        ];
        $response = Director::test($url, $data, null, 'POST');
        // Assumption: XML is default output
        $responseArr = Convert::xml2array($response->getBody());
        $this->assertEquals(42, $responseArr['Rating']);
        $this->assertNotEquals('haxx0red', $responseArr['WriteProtectedField']);
    }

    public function testFieldAliasWithPOST()
    {
        Config::inst()->set(RestfulServerTestAuthorRating::class, 'api_field_mapping', ['rate' => 'Rating']);
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestAuthorRating::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/";
        $data = [
            'rate' => '42',
        ];
        $response = Director::test($url, $data, null, 'POST');
        $responseArr = Convert::xml2array($response->getBody());
        $this->assertEquals(42, $responseArr['rate']);
    }

    public function testCanViewRespectedInList()
    {
        // Default content type
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestSecretThing::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/";
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotContains('Unspeakable', $response->getBody());

        // JSON content type
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname.json";
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotContains('Unspeakable', $response->getBody());
        $responseArray = Convert::json2array($response->getBody());
        $this->assertSame(0, $responseArray['totalSize']);

        // With authentication
        $_SERVER['PHP_AUTH_USER'] = 'editor@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'editor';
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestSecretThing::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/";
        $response = Director::test($url, null, null, 'GET');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('Unspeakable', $response->getBody());
        // Assumption: default formatter is XML
        $responseArray = Convert::xml2array($response->getBody());
        $this->assertEquals(1, $responseArray['@attributes']['totalSize']);
        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
    }

    public function testValidationErrorWithPOST()
    {
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestValidationFailure::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/";
        $data = [
            'Content' => 'Test',
        ];
        $response = Director::test($url, $data, null, 'POST');
        // Assumption: XML is default output
        $responseArr = Convert::xml2array($response->getBody());
        $this->assertEquals('SilverStripe\\ORM\\ValidationException', $responseArr['type']);
    }

    public function testExceptionThrownWithPOST()
    {
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestExceptionThrown::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname/";
        $data = [
            'Content' => 'Test',
        ];
        $response = Director::test($url, $data, null, 'POST');
        // Assumption: XML is default output
        $responseArr = Convert::xml2array($response->getBody());
        $this->assertEquals(\Exception::class, $responseArr['type']);
    }

    public function testParseClassName()
    {
        $manyMany = RestfulServerTestAuthor::config()->get('many_many');

        // simple syntax (many many standard)
        $className = RestfulServer::parseRelationClass($manyMany['RelatedPages']);
        $this->assertEquals(RestfulServerTestPage::class, $className);

        // array syntax (many many through)
        $className = RestfulServer::parseRelationClass($manyMany['SortedPages']);
        $this->assertEquals(RestfulServerTestPage::class, $className);
    }
}
