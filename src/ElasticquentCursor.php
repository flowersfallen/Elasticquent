<?php


namespace Elasticquent;

use Illuminate\Pagination\Cursor;

class ElasticquentCursor extends Cursor
{
    public function getParameters(){
        return $this->parameters;
    }
}
