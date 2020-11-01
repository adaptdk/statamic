<?php

namespace Statamic\Search;

use Statamic\Data\DataCollection;
use Statamic\Facades\Data;
use Statamic\Query\IteratorBuilder as BaseQueryBuilder;

abstract class QueryBuilder extends BaseQueryBuilder
{
    protected $query;
    protected $index;
    protected $withData = true;
    protected $total_count;

    public function __construct(Index $index)
    {
        $this->index = $index;
    }

    public function query($query)
    {
        $this->query = $query;

        return $this;
    }

    public function withData(bool $with)
    {
        $this->withData = $with;

        return $this;
    }

    public function withoutData()
    {
        $this->withData = false;

        return $this;
    }

    public function getBaseItems()
    {
        $results = $this->getSearchResults($this->query);

        if (! $this->withData) {
            return new \Statamic\Data\DataCollection($results);
        }

        return $this->collect($results)->map(function ($result) {
            return Data::find($result['id']);
        })->filter()->values();
    }

    protected function collect($items = [])
    {
        return new DataCollection($items);
    }

    protected function limitItems($items)
    {   
        $this->total_count = count($items);
        return $items->slice($this->offset, $this->limit);
    }

    public function getTotalCount()
    {  
        return $this->total_count ?? count($this->items);
    }
}
