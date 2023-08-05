<?php

namespace Elasticquent;

use Exception;
use Illuminate\Support\Arr;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Elasticquent Trait
 *
 * Functionality extensions for Elequent that
 * makes working with Elasticsearch easier.
 */
trait ElasticquentTrait
{
    use ElasticquentClientTrait;
    use ElasticquentBaseTrait;

    /**
     * Uses Timestamps In Index
     *
     * @var bool
     */
    protected $usesTimestampsInIndex = true;

    /**
     * Is ES Document
     *
     * Set to true when our model is
     * populated by a
     *
     * @var bool
     */
    protected $isDocument = false;

    /**
     * Document Score
     *
     * Hit score when using data
     * from Elasticsearch results.
     *
     * @var null|int
     */
    protected $documentScore = null;

    /**
     * Document Version
     *
     * Elasticsearch document version.
     *
     * @var null|int
     */
    protected $documentVersion = null;

    /**
     * New Collection
     *
     * @param array $models
     * @return ElasticquentCollection
     */
    public function newCollection(array $models = array())
    {
        return new ElasticquentCollection($models);
    }

    /**
     * Uses Timestamps In Index.
     */
    public function usesTimestampsInIndex()
    {
        return $this->usesTimestampsInIndex;
    }

    /**
     * Use Timestamps In Index.
     */
    public function useTimestampsInIndex($shouldUse = true)
    {
        $this->usesTimestampsInIndex = $shouldUse;
    }

    /**
     * Don't Use Timestamps In Index.
     *
     * @deprecated
     */
    public function dontUseTimestampsInIndex()
    {
        $this->useTimestampsInIndex(false);
    }



    /**
     * Is Elasticsearch Document
     *
     * Is the data in this module sourced
     * from an Elasticsearch document source?
     *
     * @return bool
     */
    public function isDocument()
    {
        return $this->isDocument;
    }

    /**
     * Get Document Score
     *
     * @return null|float
     */
    public function documentScore()
    {
        return $this->documentScore;
    }

    /**
     * Document Version
     *
     * @return null|int
     */
    public function documentVersion()
    {
        return $this->documentVersion;
    }



    /**
     * Index Documents
     *
     * Index all documents in an Eloquent model.
     *
     * @return array
     */
    public static function addAllToIndex()
    {
        $instance = new static;

        $all = $instance->newQuery()->get(array('*'));

        return $all->addToIndex();
    }

    /**
     * Re-Index All Content
     *
     * @return array
     */
    public static function reindex()
    {
        $instance = new static;

        $all = $instance->newQuery()->get(array('*'));

        return $all->reindex();
    }

    /**
     * Search By Query
     *
     * Search with a query array
     *
     * @param array $query
     * @param array $aggregations
     * @param array $sourceFields
     * @param int   $limit
     * @param int   $offset
     * @param array $sort
     * @param string   $paginationType
     *
     * @return ElasticquentResultCollection
     */
    public static function searchByQuery($query = null, $aggregations = null, $sourceFields = null, $limit = null, $offset = null, $searchAfter = null, $sort = null, $paginationType = 'base', $cursorName = 'cursor' )
    {
        $instance = new static;

        $params = $instance->getBasicEsParams(true, $limit, $offset, true);
        $perPage = $params['size'];

        if (!empty($sourceFields)) {
            $params['body']['_source']['include'] = $sourceFields;
        }

        if (!empty($query)) {
            $params['body']['query'] = $query;
        }

        if (!empty($searchAfter)) {
            $params['body']['search_after'] = $searchAfter;
        }

        if (!empty($aggregations)) {
            $params['body']['aggs'] = $aggregations;
        }

        if (!empty($sort)) {
            $params['body']['sort'] = $sort;
        }else{
            $params['body']['sort'] = $instance->getDefaultSort();
        }

        if(empty($params['body']['sort']) && $paginationType !== 'cursor'){
            throw new Exception('If use cursort pagination you must add sort direction');
        }

        if($paginationType === 'cursor'){
            list($cursor, $cursorOrders) = self::paginateUsingCursor($params, $cursorName);
        }


        $result = $instance->getElasticSearchClient()->search($params);


        return static::hydrateElasticsearchResult($result, $params, $perPage, $cursor ?? null, $cursorName, $cursorOrders ?? null);
    }

    /**
     * Perform a "complex" or custom search.
     *
     * Using this method, a custom query can be sent to Elasticsearch.
     *
     * @param  $params parameters to be passed directly to Elasticsearch
     * @return ElasticquentResultCollection
     */
    public static function complexSearch($params)
    {
        $instance = new static;

        $result = $instance->getElasticSearchClient()->search($params);

        return static::hydrateElasticsearchResult($result, $params);
    }

    /**
     * Search
     *
     * Simple search using a match _all query
     *
     * @param string $term
     * @param string $paginationType
     * @param string $cursorName
     *
     * @return ElasticquentResultCollection
     */
    public static function search($term = '', string $paginationType = 'base' , $cursorName = 'cursor')
    {
        $instance = new static;

        $params = $instance->getBasicEsParams(true, null, null, true);
        $params['body']['sort'] = $instance->getDefaultSort();

        $perPage = $params['size'];

        if($paginationType === 'cursor'){
            list($cursor, $cursorOrders) = self::paginateUsingCursor($params, $cursorName);
        }

        $params['body']['query']['match']['_all'] = $term;

        $result = $instance->getElasticSearchClient()->search($params);

        return static::hydrateElasticsearchResult($result, $params, $perPage, $cursor ?? null);
    }

    protected static function paginateUsingCursor(&$params, $cursorName = 'cursor'){


        if(!Arr::has($params,'body.sort')){
            throw new Exception('If use cursort pagination you must add sort direction');
        }

        $cursor = is_string(request()->get($cursorName))
            ? ElasticquentCursor::fromEncoded(request()->get($cursorName))
            : ElasticquentCursorPaginator::resolveCurrentCursor($cursorName, null);


        $orders = static::ensureOrderForCursorPagination(Arr::get($params,'body.sort'), ! is_null($cursor) && $cursor->pointsToPreviousItems());

        if(!is_null($cursor)){
            $params['body']['search_after'] = array_values($cursor->getParameters());
        }

        $params['body']['sort'] = $orders->toArray();

        $params['size'] += 1;

        return [$cursor, $orders];

    }

    /**
     * Add to Search Index
     *
     * @throws Exception
     * @return array
     */
    public function addToIndex()
    {
        if (!$this->exists) {
            throw new Exception('Document does not exist.');
        }

        $params = $this->getBasicEsParams();

        // Get our document body data.
        $params['body'] = $this->getIndexDocumentData();

        // The id for the document must always mirror the
        // key for this model, even if it is set to something
        // other than an auto-incrementing value. That way we
        // can do things like remove the document from
        // the index, or get the document from the index.
        $params['id'] = $this->getKey();

        return $this->getElasticSearchClient()->index($params);
    }

    /**
     * Remove From Search Index
     *
     * @return array
     */
    public function removeFromIndex()
    {
        return $this->getElasticSearchClient()->delete($this->getBasicEsParams());
    }



    /**
     * Get Search Document
     *
     * Retrieve an ElasticSearch document
     * for this entity.
     *
     * @return array
     */
    public function getIndexedDocument()
    {
        return $this->getElasticSearchClient()->get($this->getBasicEsParams());
    }



    /**
     * Build the 'fields' parameter depending on given options.
     *
     * @param bool   $getSourceIfPossible
     * @param bool   $getTimestampIfPossible
     * @return array
     */
    private function buildFieldsParameter($getSourceIfPossible, $getTimestampIfPossible)
    {
        $fieldsParam = array();

        if ($getSourceIfPossible) {
            $fieldsParam[] = '_source';
        }

        if ($getTimestampIfPossible) {
            $fieldsParam[] = '_timestamp';
        }

        return $fieldsParam;
    }









    /**
     * New From Hit Builder
     *
     * Variation on newFromBuilder. Instead, takes
     *
     * @param array $hit
     *
     * @return static
     */
    public function newFromHitBuilder($hit = array())
    {

        $key_name = $this->getKeyName();


        $attributes = $hit['_source'];

        if (isset($hit['_id'])) {
            $attributes[$key_name] = is_int($hit['_id']) ? intval($hit['_id']) : $hit['_id'];
        }

        if (isset($hit['sort'])) {
            $attributes['sort_data'] = $hit['sort'];
        }

        if(isset($hit['inner_hits'])){
            $attributes['inner_hits'] = $hit['inner_hits'];
        }


        // Add fields to attributes
        if (isset($hit['fields'])) {
            foreach ($hit['fields'] as $key => $value) {
                $attributes[$key] = $value;
            }
        }

        $instance = $this::newFromBuilderRecursive($this, $attributes);

        // In addition to setting the attributes
        // from the index, we will set the score as well.
        $instance->documentScore = $hit['_score'];

        // This is now a model created
        // from an Elasticsearch document.
        $instance->isDocument = true;

        // Set our document version if it's
        if (isset($hit['_version'])) {
            $instance->documentVersion = $hit['_version'];
        }

        return $instance;
    }

    /**
     * Create a elacticquent result collection of models from plain elasticsearch result.
     *
     * @param  array  $result
     * @param  array  $params
     * @param  null|array  $perPage
     * @param  null|\Elasticquent\ElasticquentCursor  $cursor
     * @param  null|string  $cursorName
     * @param  null|\Illuminate\Support\Collection  $cursorOrder
     *
     * @return \Elasticquent\ElasticquentResultCollection
     */
    public static function hydrateElasticsearchResult(array $result, array $params, ?int $perPage = null, ?ElasticquentCursor $cursor = null, string $cursorName = 'cursor', ?Collection $cursorOrder = null)
    {
        $items = $result['hits']['hits'];

        $instance = new static;

        $items = array_map(function ($item) use ($instance) {
            return $instance->newFromHitBuilder($item);
        }, $items);

        return $instance->newElasticquentResultCollection($items, $meta = $result, $params, $perPage, $cursor, $cursorName, $cursorOrder);

    }

    /**
     * Create a new model instance that is existing recursive.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array  $attributes
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $parentRelation
     * @return static
     */
    public static function newFromBuilderRecursive(Model $model, array $attributes = [], Relation $parentRelation = null)
    {
        $instance = $model->newInstance([], $exists = true);

        $instance->setRawAttributes((array)$attributes, $sync = true);

        // Load relations recursive
        static::loadRelationsAttributesRecursive($instance);
        // Load pivot
        static::loadPivotAttribute($instance, $parentRelation);

        return $instance;
    }

    /**
     * Create a collection of models from plain arrays recursive.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @param  \Illuminate\Database\Eloquent\Relations\Relation $parentRelation
     * @param  array $items
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function hydrateRecursive(Model $model, array $items, Relation $parentRelation = null)
    {
        $instance = $model;

        $items = array_map(function ($item) use ($instance, $parentRelation) {
            // Convert all null relations into empty arrays
            $item = $item ?: [];

            return static::newFromBuilderRecursive($instance, $item, $parentRelation);
        }, $items);

        return $instance->newCollection($items);
    }

    /**
     * Get the relations attributes from a model.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     */
    public static function loadRelationsAttributesRecursive(Model $model)
    {
        $attributes = $model->getAttributes();

        foreach ($attributes as $key => $value) {
            if (method_exists($model, $key)) {
                $reflection_method = new ReflectionMethod($model, $key);

                // Check if method class has or inherits Illuminate\Database\Eloquent\Model
                if (!static::isClassInClass("Illuminate\Database\Eloquent\Model", $reflection_method->class)) {
                    $relation = $model->$key();

                    if ($relation instanceof Relation) {
                        // Check if the relation field is single model or collections
                        if (is_null($value) === true || !static::isMultiLevelArray($value)) {
                            $value = [$value];
                        }

                        $models = static::hydrateRecursive($relation->getModel(), $value, $relation);

                        // Unset attribute before match relation
                        unset($model[$key]);
                        $relation->match([$model], $models, $key);
                    }
                }
            }
        }
    }

    /**
     * Get the pivot attribute from a model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $parentRelation
     */
    public static function loadPivotAttribute(Model $model, Relation $parentRelation = null)
    {
        $attributes = $model->getAttributes();

        foreach ($attributes as $key => $value) {
            if ($key === 'pivot') {
                unset($model[$key]);
                $pivot = $parentRelation->newExistingPivot($value);
                $model->setRelation($key, $pivot);
            }
        }
    }

    /**
     * Create a new Elasticquent Result Collection instance.
     *
     * @param  array  $models
     * @param  array  $meta
     * @param  null|array  $perPage
     * @param  null|\Elasticquent\ElasticquentCursor  $cursor
     * @param  null|string  $cursorName
     * @param  null|\Illuminate\Support\Collection  $cursorOrder
     * @return \Elasticquent\ElasticquentResultCollection
     */
    public function newElasticquentResultCollection(array $models = [], ?array $meta = null, ?array $params = [], ?int $perPage = null, ?ElasticquentCursor $cursor = null, string $cursorName = 'cursor', ?Collection $cursorOrder = null)
    {
        return new ElasticquentResultCollection($models, $meta, $params, $perPage, $cursor, $cursorName, $cursorOrder);
    }

    /**
     * Check if an array is multi-level array like [[id], [id], [id]].
     *
     * For detect if a relation field is single model or collections.
     *
     * @param  array  $array
     * @return boolean
     */
    private static function isMultiLevelArray(array $array)
    {
        foreach ($array as $key => $value) {
            if (!is_array($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check the hierarchy of the given class (including the given class itself)
     * to find out if the class is part of the other class.
     *
     * @param string $classNeedle
     * @param string $classHaystack
     * @return bool
     */
    private static function isClassInClass($classNeedle, $classHaystack)
    {
        // Check for the same
        if ($classNeedle == $classHaystack) {
            return true;
        }

        // Check for parent
        $classHaystackReflected = new \ReflectionClass($classHaystack);
        while ($parent = $classHaystackReflected->getParentClass()) {
            /**
             * @var \ReflectionClass $parent
             */
            if ($parent->getName() == $classNeedle) {
                return true;
            }
            $classHaystackReflected = $parent;
        }

        return false;
    }


    /**
     * Ensure the proper order by required for cursor pagination.
     *
     * @param  bool  $shouldReverse
     * @return \Illuminate\Support\Collection
     */
    protected static function ensureOrderForCursorPagination($sort, $shouldReverse = false)
    {
        $orders = collect($sort);

        if ($shouldReverse) {
            $orders = $orders->map(function ($order) {
                $order['order'] = $order['order'] === 'asc' ? 'desc' : 'asc';
                return $order;
            });
        }
        return $orders;
    }

    /**
     * @return string
     */
    public function getItemSortKey(): string
    {
        return $this->itemSortKey;
    }

    /**
     * @return array
     */
    public function getDefaultSort(): array
    {
        //return $this->defaultSort;
        return self::$defaultSort;
    }




}
