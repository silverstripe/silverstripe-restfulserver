<?php

namespace SilverStripe\RestfulServer\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class AuthorSortedPageRelation extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'AuthorSortedPageRelation';

    /**
     * @var array
     */
    private static $has_one = [
        'Parent' => RestfulServerTestAuthor::class,
        'SortedPage' => RestfulServerTestPage::class,
    ];

    /**
     * @var array
     */
    private static $db = [
        'Sort' => 'Int',
    ];
}
