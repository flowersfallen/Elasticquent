<?php


namespace Elasticquent;


trait ElasticquentBaseTrait
{
    use ElasticquentClientTrait;
    /**
     * Get Mapping Properties
     *
     * @return array
     */
    public function getMappingProperties()
    {
        return $this->mappingProperties;
    }

    /**
     * Set Mapping Properties
     *
     * @param    array $mapping
     * @internal param array $mapping
     */
    public function setMappingProperties(array $mapping = null)
    {
        $this->mappingProperties = $mapping;
    }

    /**
     * Get Index Settings
     *
     * @return array
     */
    public function getIndexSettings()
    {
        return $this->indexSettings;
    }

    /**
     * Mapping Exists
     *
     * @return bool
     */
    public static function mappingExists()
    {
        $instance = new static;

        $mapping = $instance->getMapping();

        return (empty($mapping)) ? false : true;
    }

    /**
     * Get Mapping
     *
     * @return void
     */
    public static function getMapping()
    {
        $instance = new static;

        $params = $instance->getBasicEsParams();

        return $instance->getElasticSearchClient()->indices()->getMapping($params);
    }


    /**
     * Put Mapping.
     *
     * @param bool $ignoreConflicts
     *
     * @return array
     */
    public static function putMapping($ignoreConflicts = false)
    {
        $instance = new static;

        $mapping = $instance->getBasicEsParams();

        $params = array(
            '_source' => array('enabled' => true),
            'properties' => $instance->getMappingProperties(),
        );

        $mapping['body'] = $params;

        return $instance->getElasticSearchClient()->indices()->putMapping($mapping);
    }


    /**
     * Create Index
     *
     * @param int $shards
     * @param int $replicas
     *
     * @return array
     */
    public static function createIndex($addMapping = true, $shards = null, $replicas = null)
    {
        $instance = new static;

        $client = $instance->getElasticSearchClient();

        $index = array(
            'index' => $instance->getIndexName(),
        );

        $settings = $instance->getIndexSettings();
        if (!is_null($settings)) {
            $index['body']['settings'] = $settings;
        }

        if (!is_null($shards)) {
            $index['body']['settings']['number_of_shards'] = $shards;
        }

        if (!is_null($replicas)) {
            $index['body']['settings']['number_of_replicas'] = $replicas;
        }

        if($addMapping){
            $mappingProperties = $instance->getMappingProperties();
            if (!is_null($mappingProperties)) {
                $index['body']['mappings'] = [
                    '_source' => array('enabled' => true),
                    'properties' => $mappingProperties,
                ];
            }
        }


        return $client->indices()->create($index);
    }

    /**
     * Delete Index
     *
     * @return array
     */
    public static function deleteIndex()
    {
        $instance = new static;

        $client = $instance->getElasticSearchClient();

        $index = array(
            'index' => $instance->getIndexName(),
        );

        return $client->indices()->delete($index);
    }

    /**
     * Index exist
     *
     * @return bool
     */
    public static function indexExist()
    {
        $instance = new static;

        $client = $instance->getElasticSearchClient();

        $index = array(
            'index' => $instance->getIndexName(),
        );

        return $client->indices()->exists($index);
    }


    public static function putSettings(){

        $instance = new static;

        $client = $instance->getElasticSearchClient();

        $index = array(
            'index' => $instance->getIndexName(),
        );

        $settings = $instance->getIndexSettings();
        if (!is_null($settings)) {
            $index['body']['settings'] = $settings;
        }

        $client->indices()->putSettings($index);
    }

    /**
     * Partial Update to Indexed Document
     *
     * @return array
     */
    public function updateIndex()
    {
        $params = $this->getBasicEsParams();

        // Get our document body data.
        $params['body']['doc'] = $this->getIndexDocumentData();

        return $this->getElasticSearchClient()->update($params);
    }

    /**
     * Get Basic Elasticsearch Params
     *
     * Most Elasticsearch API calls need the index and
     * type passed in a parameter array.
     *
     * @param bool $getIdIfPossible
     * @param bool $getSourceIfPossible
     * @param bool $getTimestampIfPossible
     * @param int  $limit
     * @param int  $offset
     *
     * @return array
     */
    public function getBasicEsParams($getIdIfPossible = true, $limit = null, $offset = null, $allowDefLimit = false)
    {
        $params = array(
            'index' => $this->getIndexName(),
        );

        if ($getIdIfPossible && $this->getKey()) {
            $params['id'] = $this->getKey();
        }

        if (is_numeric($limit)) {
            $params['size'] = $limit;
        }else if($allowDefLimit){
            $params['size'] = $this->getPerPage();
        }

        if (is_numeric($offset)) {
            $params['from'] = $offset;
        }

        return $params;
    }


  


}
