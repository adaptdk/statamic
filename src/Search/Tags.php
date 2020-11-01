<?php

namespace Statamic\Search;

use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Tags\Concerns;
use Statamic\Tags\Tags as BaseTags;
use Illuminate\Pagination\Paginator;
use Statamic\Extensions\Pagination\LengthAwarePaginator;

class Tags extends BaseTags
{
    use Concerns\OutputsItems,
        Concerns\QueriesConditions;

    protected static $handle = 'search';

    public function results()
    {

        if (! $query = request($this->params->get('query', 'q'))) {
            return $this->parseNoResults();
        }
        
        $offset = $this->params->get('offset');
        if ($this->params->get('paginate')) {
            $page = request('page') ?? 1;
            $offset = ($page - 1) * 5;
        }
        
        $builder = Search::index($this->params->get('index'))
            ->ensureExists()
            ->search($query)
            ->withData($this->params->get('supplement_data', true))
            ->limit($this->params->get('limit'))
            ->offset($offset);

        $this->querySite($builder);
        $this->queryStatus($builder);
        $this->queryConditions($builder);
        
        $results = $this->addResultTypes($builder->get());

        if ($this->params->get('paginate')) {
            $results = new LengthAwarePaginator(
                $results,
                $builder->getTotalCount(),
                $this->params->get('limit'),
                request('page') ?? 1,
                [
                    'path' => Paginator::resolveCurrentPath(),
                    'query' => ['q' => $query],
                ]
            );
        }

        return $this->output($results);
    }

    protected function addResultTypes($results)
    {
        return $results->map(function ($result) {
            $type = null;

            if ($result instanceof \Statamic\Contracts\Entries\Entry) {
                $type = 'entry';
            } elseif ($result instanceof \Statamic\Contracts\Taxonomies\Term) {
                $type = 'term';
            } elseif ($result instanceof \Statamic\Contracts\Assets\Asset) {
                $type = 'asset';
            }

            $result->set('result_type', $type);

            return $result;
        });
    }

    protected function queryStatus($query)
    {
        if ($this->isQueryingCondition('status') || $this->isQueryingCondition('published')) {
            return;
        }

        return $query->where('status', 'published');
    }

    protected function querySite($query)
    {
        $site = $this->params->get(['site', 'locale'], Site::current()->handle());

        if ($site === '*' || ! Site::hasMultiple()) {
            return;
        }

        return $query->where('site', $site);
    }
}
