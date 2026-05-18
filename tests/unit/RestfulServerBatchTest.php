<?php

namespace SilverStripe\RestfulServer\Tests;

use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestComment;
use SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestPage;
use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

class RestfulServerBatchTest extends SapphireTest
{
    protected static $fixture_file = 'RestfulServerTest.yml';

    protected $baseURI = 'http://www.fakesite.test';

    protected static $extra_dataobjects = [
        RestfulServerTestComment::class,
        RestfulServerTestPage::class,
        \SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestAuthor::class,
        \SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestAuthorRating::class,
        \SilverStripe\RestfulServer\Tests\Stubs\RestfulServerTestSecretThing::class,
    ];

    protected function urlSafeClassname($classname)
    {
        return str_replace('\\', '-', $classname ?? '');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Director::config()->set('alternate_base_url', $this->baseURI);
    }

    public function testBatchPostJSON()
    {
        $_SERVER['PHP_AUTH_USER'] = 'editor@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'editor';
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname.json";

        $body = file_get_contents(__DIR__ . '/fixtures/batch/json/create_batch.json');

        $response = Director::test($url, null, null, 'POST', $body);
        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertCount(2, $data);
        $this->assertEquals('Batch 1', $data[0]['Name']);
        $this->assertEquals('Batch 2', $data[1]['Name']);

        $this->assertNotNull(RestfulServerTestComment::get()->filter('Name', 'Batch 1')->first());
        $this->assertNotNull(RestfulServerTestComment::get()->filter('Name', 'Batch 2')->first());
    }

    public function testBatchPutJSON()
    {
        $_SERVER['PHP_AUTH_USER'] = 'editor@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'editor';
        $c1 = $this->objFromFixture(RestfulServerTestComment::class, 'comment1');

        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname.json";

        $body = file_get_contents(__DIR__ . '/fixtures/batch/json/update_batch.json');
        $body = str_replace('ID_HOLDER', $c1->ID, $body);

        $response = Director::test($url, null, null, 'PUT', $body);
        $this->assertEquals(202, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertCount(1, $data);
        $this->assertEquals('Updated 1', $data[0]['Name']);

        $c1->flushCache();
        $this->assertEquals('Updated 1', RestfulServerTestComment::get()->byID($c1->ID)->Name);
    }

    public function testBatchPostXML()
    {
        $_SERVER['PHP_AUTH_USER'] = 'editor@test.com';
        $_SERVER['PHP_AUTH_PW'] = 'editor';
        $urlSafeClassname = $this->urlSafeClassname(RestfulServerTestComment::class);
        $url = "{$this->baseURI}/api/v1/$urlSafeClassname.xml";

        $xml = file_get_contents(__DIR__ . '/fixtures/batch/xml/create_batch.xml');

        $response = Director::test($url, null, null, 'POST', $xml);
        $this->assertEquals(201, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertStringContainsString('XML Batch 1', $body);
        $this->assertStringContainsString('XML Batch 2', $body);

        $this->assertNotNull(RestfulServerTestComment::get()->filter('Name', 'XML Batch 1')->first());
        $this->assertNotNull(RestfulServerTestComment::get()->filter('Name', 'XML Batch 2')->first());
    }
}
