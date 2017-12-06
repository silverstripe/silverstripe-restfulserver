<?php

namespace SilverStripe\RestfulServer;

use SilverStripe\ORM\SS_List;

/**
 * Restful server handler for a single DataObject
 */
class RestfulServerItem
{
    private static $url_handlers = array(
        '$Relation' => 'handleRelation',
    );

    public function __construct($item)
    {
        $this->item = $item;
    }

    public function handleRelation($request)
    {
        $funcName = $request('Relation');
        $relation = $this->item->$funcName();

        if ($relation instanceof SS_List) {
            return new RestfulServerList($relation);
        } else {
            return new RestfulServerItem($relation);
        }
    }
}
