<?php

namespace SilverStripe\RestfulServer;

/**
 * Restful server handler for a SS_List
 */
class RestfulServerList
{
    private static $url_handlers = array(
        '#ID' => 'handleItem',
    );

    public function __construct($list)
    {
        $this->list = $list;
    }

    public function handleItem($request)
    {
        return new RestfulServerItem($this->list->getById($request->param('ID')));
    }
}
