<?php namespace Elasticquent;


use Elasticquent\ElasticquentPaginator as Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Elasticquent\ElasticquentCursor;
use Elasticquent\ElasticquentCursorPaginator;



class ElasticquentResultCollection extends \Illuminate\Database\Eloquent\Collection
{
    protected $took;
    protected $timed_out;
    protected $shards;
    protected $hits;
    protected $aggregations = null;
    protected $params = [];
    protected $perPage;
    protected $cursor;
    protected $cursorName;
    protected $cursorOrders;





    /**
     * Create a new instance containing Elasticsearch results
     *
     * @todo Remove backwards compatible detection at further point
     * @deprecated Initialize with params ($results, $instance) is deprecated,
     *    please use Model::hydrateElasticsearchResult($results).
     *
     * @param  mixed  $items
     * @param  array  $meta
     * @return void
     */
    public function __construct($items, ?array $meta = null, ?array $params = [], ?int $perPage = null, ?ElasticquentCursor $cursor = null, ?string $cursorName = 'cursor', ?Collection $cursorOrders = null)
    {
        // Detect if arguments are old deprecated version ($results, $instance)
        if (isset($items['hits']) and $meta instanceof \Illuminate\Database\Eloquent\Model) {
            $instance = $meta;
            $meta = $items;
            $items = $instance::hydrateElasticsearchResult($meta);
        }

        parent::__construct($items);

        // Take our result meta and map it
        // to some class properties.
        if (is_array($meta)) {
            $this->setMeta($meta);
        }


        if(is_array($params)){
            $this->setParams($params);
        }

        if(is_int($perPage)){
            $this->setPerPage($perPage);
        }

        if(is_a($cursor, ElasticquentCursor::class, true)){
            $this->setCursor($cursor);
        }

        if(is_string($cursorName)){
            $this->setCursorName($cursorName);
        }

        if(is_a($cursorOrders, Collection::class, true)){
            $this->setCursorOrders($cursorOrders);
        }

    }

    /**
     * Set the result meta.
     *
     * @param array $meta
     * @return $this
     */
    public function setMeta(array $meta)
    {
        $this->took = isset($meta['took']) ? $meta['took'] : null;
        $this->timed_out = isset($meta['timed_out']) ? $meta['timed_out'] : null;
        $this->shards = isset($meta['_shards']) ? $meta['_shards'] : null;
        $this->hits = isset($meta['hits']) ? $meta['hits'] : null;
        $this->aggregations = isset($meta['aggregations']) ? $meta['aggregations'] : [];

        return $this;
    }

    private function setParams($params): void
    {
        $this->params = $params;
    }

    private function setPerPage($perPage): void
    {

        $this->perPage = $perPage;
    }


    private function setCursor($cursor): void
    {
        $this->cursor = $cursor;
    }

    private function setCursorName(string $cursorName): void
    {
        $this->cursorName = $cursorName;
    }


    private function setCursorOrders(Collection $cursorOrders): void
    {
        $this->cursorOrders = $cursorOrders;
    }

    /**
     * Total Hits
     *
     * @return int
     */
    public function totalHits()
    {
        return $this->hits['total']['value'];
    }

    /**
     * Max Score
     *
     * @return float
     */
    public function maxScore()
    {
        return $this->hits['max_score'];
    }

    /**
     * Get Shards
     *
     * @return array
     */
    public function getShards()
    {
        return $this->shards;
    }

    /**
     * Took
     *
     * @return string
     */
    public function took()
    {
        return $this->took;
    }

    /**
     * Timed Out
     *
     * @return bool
     */
    public function timedOut()
    {
        return (bool) $this->timed_out;
    }

    /**
     * Get Hits
     *
     * Get the raw hits array from
     * Elasticsearch results.
     *
     * @return array
     */
    public function getHits()
    {
        return $this->hits;
    }

    /**
     * Get aggregations
     *
     * Get the raw hits array from
     * Elasticsearch results.
     *
     * @return array
     */
    public function getAggregations()
    {
        return $this->aggregations;
    }

    /**
     * Paginate Collection
     *
     * @param int $pageLimit
     *
     * @return Paginator
     */
    public function paginate($pageLimit = 25)
    {
        $page = Paginator::resolveCurrentPage() ?: 1;

        return new Paginator($this->items, $this->hits, $this->totalHits(), $pageLimit, $page, ['path' => Paginator::resolveCurrentPath()]);
    }

    /*
     * Аналог cursor pagination c eloqument для elasticsearch
     * на основе search_after, обезательно наличие сортировки в параметрах
    */

    public function paginateCursor($itemSortKey = 'sort_data'){


        return new ElasticquentCursorPaginator($this->items, $this->perPage, $this->cursor, [
            'path' => Paginator::resolveCurrentPath(),
            'cursorName' => $this->cursorName,
            'parameters' => $this->cursorOrders->keys(),
            'itemSortKey' => $itemSortKey,
        ]);

    }





}
