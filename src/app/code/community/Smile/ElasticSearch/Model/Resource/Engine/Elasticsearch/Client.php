<?php
/**
 * Elastic search client implementation.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * This work is a fork of Johann Reinke <johann@bubblecode.net> previous module
 * available at https://github.com/jreinke/magento-elasticsearch
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */

// Include the Elastica library used by the client
require_once 'Elastica/autoload.php';

/**
 * Implementation of the Elastica client
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Client extends \Elastica\Client
{
    /**
     * @var string Index name.
     */
    protected $_indexName;

    protected $_oldIndex = false;

    /**
     * @var string Date format.
     * @link http://www.elasticsearch.org/guide/reference/mapping/date-format.html
     */
    protected $_dateFormat = 'date';

    /**
     * @var array Stop languages for token filter.
     * @link http://www.elasticsearch.org/guide/reference/index-modules/analysis/stop-tokenfilter.html
     */
    protected $_stopLanguages = array(
        'arabic', 'armenian', 'basque', 'brazilian', 'bulgarian', 'catalan', 'czech',
        'danish', 'dutch', 'english', 'finnish', 'french', 'galician', 'german', 'greek',
        'hindi', 'hungarian', 'indonesian', 'italian', 'norwegian', 'persian', 'portuguese',
        'romanian', 'russian', 'spanish', 'swedish', 'turkish',
    );

    /**
     * @var array Snowball languages.
     * @link http://www.elasticsearch.org/guide/reference/index-modules/analysis/snowball-tokenfilter.html
     */
    protected $_snowballLanguages = array(
        'Armenian', 'Basque', 'Catalan', 'Danish', 'Dutch', 'English', 'Finnish', 'French',
        'German', 'Hungarian', 'Italian', 'Kp', 'Lovins', 'Norwegian', 'Porter', 'Portuguese',
        'Romanian', 'Russian', 'Spanish', 'Swedish', 'Turkish',
    );

    /**
     * Initializes search engine config and index name.
     *
     * @param array|bool $params Client init params.
     */
    public function __construct($params = false)
    {
        $config = $this->_getHelper()->getEngineConfigData();
        parent::__construct($config);
        if (!isset($config['alias'])) {
            Mage::throwException('Alias must be defined for search engine client.');
        }
        $this->setIndexName($config['alias']);
    }

    /**
     * Cleans index.
     *
     * @param int    $storeId Store id
     * @param int    $id      Document id
     * @param string $type    Type of document
     *
     * @return mixed
     */
    public function cleanIndex($storeId = null, $id = null, $type = 'product')
    {
        $this->_prepareIndex();
        if ($this->getIndex($this->_indexName)->exists()) {
            if (null === $storeId) {
                // no store filter
                if (empty($id)) {
                    // delete ALL docs of type $type
                    return $this->getIndex($this->_indexName)->getType($type)->delete();
                } else {
                    // delete docs of type $type with _id in $id
                    foreach (Mage::app()->getStores() as $store) {
                        $this->cleanIndex($store->getId(), $id, $type);
                    }
                }
            } else {
                if (empty($id)) {
                    // delete ALL docs from specific store
                    $path = sprintf('%s/%s/_query', $this->_indexName, $type);
                    $query = new \Elastica\Query\Term();
                    $query->setTerm('store_id', $storeId);
                    $response = $this->request($path, \Elastica\Request::DELETE, $query->toArray());

                    return new \Elastica\ResultSet($response);
                } else {
                    // delete docs from specific store with _id in $id
                    $ids = (array) $id;
                    foreach ($ids as &$id) {
                        $id .= '|' . $storeId;
                    }
                    unset($id);

                    return $this->deleteIds($ids, $this->_indexName, $type);
                }
            }
        }

        return $this;
    }

    /**
     * Create document to index.
     *
     * @param string $id   Document Id
     * @param array  $data Data indexed
     * @param string $type Document type
     *
     * @return \Elastica\Document
     */
    public function createDoc($id = '', array $data = array(), $type = 'product')
    {
        return new \Elastica\Document($id, $data, $type, $this->_indexName);
    }

    /**
     * Deletes index.
     *
     * @return bool|\Elastica\Response
     */
    public function deleteIndex()
    {
        if ($this->getStatus()->indexExists($this->_indexName)) {
            return $this->getIndex($this->_indexName)->delete();
        }

        return true;
    }

    /**
     * Returns facets max size parameter.
     *
     * @return int
     */
    public function getFacetsMaxSize()
    {
        return (int) $this->getConfig('facets_max_size');
    }

    /**
     * Returns fuzzy max query terms parameter.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return int
     */
    public function getFuzzyMaxQueryTerms()
    {
        return (int) $this->getConfig('fuzzy_max_query_terms');
    }

    /**
     * Returns fuzzy min similarity parameter.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return float
     */
    public function getFuzzyMinSimilarity()
    {
        // 0 to 1 (1 excluded)
        return min(0.99, max(0, $this->getConfig('fuzzy_min_similarity')));
    }

    /**
     * Returns fuzzy prefix length.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return int
     */
    public function getFuzzyPrefixLength()
    {
        return (int) $this->getConfig('fuzzy_prefix_length');
    }

    /**
     * Returns fuzzy query boost parameter.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return float
     */
    public function getFuzzyQueryBoost()
    {
        return (float) $this->getConfig('fuzzy_query_boost');
    }

    /**
     * Checks if fuzzy query is enabled.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return bool
     */
    public function isFuzzyQueryEnabled()
    {
        return (bool) $this->getConfig('enable_fuzzy_query');
    }

    /**
     * Checks if ICU folding is enabled.
     *
     * @link http://www.elasticsearch.org/guide/reference/index-modules/analysis/icu-plugin.html
     * @return bool
     */
    public function isIcuFoldingEnabled()
    {
        return (bool) $this->getConfig('enable_icu_folding');
    }

    /**
     * Refreshes index
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Client
     */
    public function refreshIndex()
    {
        $index = $this->getIndex($this->_indexName);

        if ($index->exists()) {
            $this->getIndex($this->_indexName)->refresh();
        }

        return $this;
    }

    /**
     * Handles search and facets.
     *
     * @param string $q      Fulltext query
     * @param array  $params Query params (filters, facets, ...)
     * @param string $type   Type of document
     *
     * @return Elastica_ResultSet
     *
     * @throws Exception
     */
    public function search($q, $params = array(), $type = 'product')
    {
        if ($this->getIndex($this->_indexName)->exists()) {
            Varien_Profiler::start('ELASTICA_SEARCH');
            if (empty($params['filters'])) {
                $params['filters'] = '*';
            }
            $queryFilter = new \Elastica\Filter\Query(new \Elastica\Query\QueryString($params['filters']));
            if (isset($params['range_filters']) && !empty($params['range_filters'])) {
                $andFilter = new \Elastica\Filter\BoolAnd();
                $andFilter->addFilter($queryFilter);
                $filter = new \Elastica\Filter\Range();
                foreach ($params['range_filters'] as $field => $rangeFilter) {
                    $filter->addField($field, $rangeFilter);
                }
                $andFilter->addFilter($filter);
                $queryFilter = $andFilter;
            }

            if (empty($q)) {
                $baseQuery = new \Elastica\Query\MatchAll();
            } else {
                $baseQuery = new \Elastica\Query\Bool();

                if ($this->isFuzzyQueryEnabled()) {
                    $fields = $this->_getSearchFields(true, $q);
                    $queryFuzzy = new \Elastica\Query\FuzzyLikeThis();
                    $queryFuzzy->addFields($fields);
                    $queryFuzzy->setLikeText($q);
                    $queryFuzzy->setMinSimilarity($this->getFuzzyMinSimilarity());
                    $queryFuzzy->setPrefixLength($this->getFuzzyPrefixLength());
                    $queryFuzzy->setMaxQueryTerms($this->getFuzzyMaxQueryTerms());
                    $queryFuzzy->setBoost($this->getFuzzyQueryBoost());
                    $baseQuery->addShould($queryFuzzy);
                }

                $queryString = new \Elastica\Query\QueryString($q);

                $queryString->setFields($this->_getSearchFields(false, $q));
                $baseQuery->addShould($queryString);;
            }

            $filteredQuery = new \Elastica\Query\Filtered($baseQuery, $queryFilter);
            $query = \Elastica\Query::create($filteredQuery)
                ->setFrom($params['offset'])
                ->setLimit($params['limit']);

            if (isset($params['facets']['queries']) && !empty($params['facets']['queries'])) {
                foreach ($params['facets']['queries'] as $facetQuery) {
                    $facet = new \Elastica\Facet\Query($facetQuery);
                    $facet->setParam('query_string', array('query' => $facetQuery));
                    $query->addFacet($facet);
                }
            }

            if (isset($params['stats']['fields']) && !empty($params['stats']['fields'])) {
                foreach ($params['stats']['fields'] as $field) {
                    $facet = new \Elastica\Facet\Statistical($field);
                    $facet->setParam('field', $field);
                    $query->addFacet($facet);
                }
            } else {
                if (isset($params['facets']['fields']) && !empty($params['facets']['fields'])) {
                    $properties = $this->_getIndexProperties();
                    foreach ($params['facets']['fields'] as $field) {
                        if (array_key_exists($field, $properties)) {
                            $facet = new \Elastica\Facet\Terms($field);
                            if ($properties[$field]['type'] == 'multi_field') {
                                $field .= '.untouched';
                            }
                            $facet->setField($field);
                            $facet->setParam('all_terms', true);
                            $facet->setSize($this->getFacetsMaxSize());
                            $query->addFacet($facet);
                        }
                    }
                }

                if (isset($params['facets']['ranges']) && !empty($params['facets']['ranges'])) {
                    foreach ($params['facets']['ranges'] as $field => $ranges) {
                        $facet = new \Elastica\Facet\Range($field);
                        $facet->setField($field);
                        $facet->setRanges($ranges);
                        $query->addFacet($facet);
                    }
                }
            }

            if (isset($params['sort']) && !empty($params['sort'])) {
                foreach ($params['sort'] as $sort) {
                    $query->addSort($sort);
                }
            }

            $result = $this->getIndex($this->_indexName)
                ->getType($type)
                ->search($query);

            Varien_Profiler::stop('ELASTICA_SEARCH');

            return $result;
        }

        return array();
    }

    /**
     * Stores index name.
     *
     * @param \Elastica\Index $index Index to be set
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Client
     */
    public function setIndexName($index)
    {
        $this->_indexName = $index;

        return $this;
    }

    /**
     * Returns attribute type for indexation.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute Attribute
     *
     * @return string
     */
    protected function _getAttributeType($attribute)
    {
        $type = 'string';
        if ($attribute->getBackendType() == 'decimal') {
            $type = 'double';
        } elseif ($attribute->getSourceModel() == 'eav/entity_attribute_source_boolean') {
            $type = 'boolean';
        } elseif ($attribute->getBackendType() == 'datetime') {
            $type = 'date';
        } elseif ($attribute->usesSource() || $attribute->getFrontendClass() == 'validate-digits') {
            $type = 'string';
        }

        return $type;
    }

    /**
     * Returns search helper.
     *
     * @return Smile_ElasticSearch_Helper_Elasticsearch
     */
    protected function _getHelper()
    {
        return Mage::helper('smile_elasticsearch/elasticsearch');
    }

    /**
     * Builds index properties for indexation according to available attributes and stores.
     *
     * @return array
     */
    protected function _getIndexProperties()
    {
        $cacheId = Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch::CACHE_INDEX_PROPERTIES_ID;
        if ($properties = Mage::app()->loadCache($cacheId)) {
            return unserialize($properties);
        }

        /** @var $helper Smile_ElasticSearch_Helper_Data */
        $helper = $this->_getHelper();
        $indexSettings = $this->_getIndexSettings();
        $properties = array();

        $attributes = $helper->getSearchableAttributes(array('varchar', 'int'));
        foreach ($attributes as $attribute) {
            /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            if ($this->_isAttributeIndexable($attribute)) {
                foreach (Mage::app()->getStores() as $store) {
                    /** @var $store Mage_Core_Model_Store */
                    $locale = $helper->getLocaleCode($store);
                    $key = $helper->getAttributeFieldName($attribute, $locale);
                    $type = $this->_getAttributeType($attribute);
                    if ($type !== 'string') {
                        $properties[$key] = array(
                            'type' => $type,
                        );
                    } else {
                        $weight = $attribute->getSearchWeight();
                        $properties[$key] = array(
                            'type' => 'multi_field',
                            'fields' => array(
                                $key => array(
                                    'type' => $type,
                                    'boost' => $weight > 0 ? $weight : 1,
                                ),
                                'untouched' => array(
                                    'type' => $type,
                                    'index' => 'not_analyzed',
                                ),
                            ),
                        );
                        foreach (array_keys($indexSettings['analysis']['analyzer']) as $analyzer) {
                            $properties[$key]['fields'][$analyzer] = array(
                                'type' => 'string',
                                'analyzer' => $analyzer,
                                'boost' => $attribute->getSearchWeight(),
                            );
                        }
                    }
                }
            }
        }

        $attributes = $helper->getSearchableAttributes('text');
        foreach ($attributes as $attribute) {
            /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            foreach (Mage::app()->getStores() as $store) {
                /** @var $store Mage_Core_Model_Store */
                $languageCode = $helper->getLanguageCodeByStore($store);
                $locale = $helper->getLocaleCode($store);
                $key = $helper->getAttributeFieldName($attribute, $locale);
                $weight = $attribute->getSearchWeight();
                $properties[$key] = array(
                    'type' => 'string',
                    'boost' => $weight > 0 ? $weight : 1,
                    'analyzer' => 'analyzer_' . $languageCode,
                );
            }
        }

        $attributes = $helper->getSearchableAttributes(array('static', 'varchar', 'decimal', 'datetime'));
        foreach ($attributes as $attribute) {
            /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            $key = $helper->getAttributeFieldName($attribute);
            if ($this->_isAttributeIndexable($attribute) && !isset($properties[$key])) {
                $weight = $attribute->getSearchWeight();
                $properties[$key] = array(
                    'type' => $this->_getAttributeType($attribute),
                    'boost' => $weight > 0 ? $weight : 1,
                );
                if ($attribute->getBackendType() == 'datetime') {
                    $properties[$key]['format'] = $this->_dateFormat;
                }
            }
        }

        // Handle sortable attributes
        $attributes = $helper->getSortableAttributes();
        foreach ($attributes as $attribute) {
            /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            $type = 'string';
            if ($attribute->getBackendType() == 'decimal') {
                $type = 'double';
            } elseif ($attribute->getBackendType() == 'datetime') {
                $type = 'date';
                $format = $this->_dateFormat;
            }
            foreach (Mage::app()->getStores() as $store) {
                /** @var $store Mage_Core_Model_Store */
                $locale = $helper->getLocaleCode($store);
                $key = $helper->getSortableAttributeFieldName($attribute, $locale);
                if (!array_key_exists($key, $properties)) {
                    $properties[$key] = array(
                        'type' => $type,
                        'index' => 'not_analyzed',
                    );
                    if (isset($format)) {
                        $properties[$key]['format'] = $format;
                    }
                }
            }
        }

        // Custom attributes indexation
        $properties['visibility'] = array(
            'type' => 'integer',
        );
        $properties['store_id'] = array(
            'type' => 'integer',
        );
        $properties['in_stock'] = array(
            'type' => 'boolean',
        );

        if (Mage::app()->useCache('config')) {
            $lifetime = $this->_getHelper()->getCacheLifetime();
            Mage::app()->saveCache(serialize($properties), $cacheId, array('config'), $lifetime);
        }

        foreach (Mage::app()->getStores() as $store) {
            $properties[$helper->getSuggestFieldName($store)] = array(
                'type'     => 'completion',
                'payloads' => true
            );
        }
        
        return $properties;
    }
    

    /**
     * Returns indexation analyzers and filters configuration.
     *
     * @return array
     */
    protected function _getIndexSettings()
    {
        $indexSettings = array();
        $indexSettings['number_of_replicas'] = (int) $this->getConfig('number_of_replicas');
        $indexSettings['analysis']['analyzer'] = array(
            'whitespace' => array(
                'tokenizer' => 'standard',
                'filter' => array('lowercase'),
            ),
            'edge_ngram_front' => array(
                'tokenizer' => 'standard',
                'filter' => array('length', 'edge_ngram_front', 'lowercase'),
            ),
            'edge_ngram_back' => array(
                'tokenizer' => 'standard',
                'filter' => array('length', 'edge_ngram_back', 'lowercase'),
            ),
            'shingle' => array(
                'tokenizer' => 'standard',
                'filter' => array('shingle', 'length', 'lowercase'),
            ),
            'shingle_strip_ws' => array(
                'tokenizer' => 'standard',
                'filter' => array('shingle', 'strip_whitespaces', 'length', 'lowercase'),
            ),
            'shingle_strip_apos_and_ws' => array(
                'tokenizer' => 'standard',
                'filter' => array('shingle', 'strip_apostrophes', 'strip_whitespaces', 'length', 'lowercase'),
            ),
        );
        $indexSettings['analysis']['filter'] = array(
            'shingle' => array(
                'type' => 'shingle',
                'max_shingle_size' => 20,
                'output_unigrams' => true,
            ),
            'strip_whitespaces' => array(
                'type' => 'pattern_replace',
                'pattern' => '\s',
                'replacement' => '',
            ),
            'strip_apostrophes' => array(
                'type' => 'pattern_replace',
                'pattern' => "'",
                'replacement' => '',
            ),
            'edge_ngram_front' => array(
                'type' => 'edgeNGram',
                'min_gram' => 3,
                'max_gram' => 10,
                'side' => 'front',
            ),
            'edge_ngram_back' => array(
                'type' => 'edgeNGram',
                'min_gram' => 3,
                'max_gram' => 10,
                'side' => 'back',
            ),
            'length' => array(
                'type' => 'length',
                'min' => 2,
            ),
        );
        /** @var $helper Smile_ElasticSearch_Helper_Data */
        $helper = $this->_getHelper();
        foreach (Mage::app()->getStores() as $store) {
            /** @var $store Mage_Core_Model_Store */
            $languageCode = $helper->getLanguageCodeByStore($store);
            $lang = Zend_Locale_Data::getContent('en_GB', 'language', $helper->getLanguageCodeByStore($store));
            if (!in_array($lang, $this->_snowballLanguages)) {
                continue; // language not present by default in elasticsearch
            }
            $indexSettings['analysis']['analyzer']['analyzer_' . $languageCode] = array(
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => array('length', 'lowercase', 'snowball_' . $languageCode),
            );
            $indexSettings['analysis']['filter']['snowball_' . $languageCode] = array(
                'type' => 'snowball',
                'language' => $lang,
            );
        }

        if ($this->isIcuFoldingEnabled()) {
            foreach ($indexSettings['analysis']['analyzer'] as &$analyzer) {
                array_unshift($analyzer['filter'], 'icu_folding');
            }
            unset($analyzer);
        }

        return $indexSettings;
    }

    /**
     * Retrieves searchable fields according to text query.
     *
     * @param bool   $onlyFuzzy Return only field used for fuzzy matching
     * @param string $q         Search query
     *
     * @return array
     */
    protected function _getSearchFields($onlyFuzzy = false, $q = '')
    {
        $properties = $this->_getIndexProperties();
        $fields = array();
        foreach ($properties as $key => $property) {
            if ($property['type'] == 'date'
                || ($property['type'] == 'multi_field' && $property['fields'][$key]['type'] == 'date')) {
                continue;
            }
            if (!is_bool($q)
                && ($property['type'] == 'boolean'
                    || ($property['type'] == 'multi_field' && $property['fields'][$key]['type'] == 'boolean'))) {
                continue;
            }
            if (!is_integer($q)
                && ($property['type'] == 'integer'
                    || ($property['type'] == 'multi_field' && $property['fields'][$key]['type'] == 'integer'))) {
                continue;
            }
            if (!is_double($q)
                && ($property['type'] == 'double'
                    || ($property['type'] == 'multi_field' && $property['fields'][$key]['type'] == 'double'))) {
                continue;
            }
            if (!$onlyFuzzy && $property['type'] == 'multi_field') {
                foreach (array_keys($property['fields']) as $field) {
                    if (strpos($field, 'edge_ngram') !== 0) {
                        $fields[] = $key . '.' . $field;
                    }
                }
            } elseif (0 !== strpos($key, 'sort_by_')) {
                $fields[] = $key;
            }
        }

        if ($this->_getHelper()->shouldSearchOnOptions()) {
            // Search on options labels too
            $fields[] = '_options';
        }

        return $fields;
    }

    /**
     * Checks if attribute is indexable.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute Attribute
     *
     * @return bool
     */
    protected function _isAttributeIndexable($attribute)
    {
        return $this->_getHelper()->isAttributeIndexable($attribute);
    }

    /**
     * Creates or updates Elasticsearch index.
     *
     * @link http://www.elasticsearch.org/guide/reference/mapping/core-types.html
     * @link http://www.elasticsearch.org/guide/reference/mapping/multi-field-type.html
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Client
     *
     * @throws Exception
     */
    protected function _prepareIndex()
    {
        try {
            $indexSettings = $this->_getIndexSettings();
            $index = $this->getIndex($this->_indexName);
            if (!$index->exists()) {
                $indexSettings['number_of_shards'] = (int) $this->getConfig('number_of_shards');
                $index->create($indexSettings);
            } else {
                $index->close();
                $index->setSettings($indexSettings);
                $index->open();
            }
            $mapping = new Elastica\Type\Mapping();
            $mapping->setType($index->getType('product'));
            $mapping->setProperties($this->_getIndexProperties());
            $mapping->send();
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            throw $e;
        }

        return $this;
    }

    /**
     * Prepare a new index for full reindex
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Client Self Reference
     */
    public function prepareNewIndex()
    {
        // Current date use to compute the index name
        $currentDate = new Zend_Date();

        // Default pattern if nothing set into the config
        $pattern = 'magento-{{YYYYMMdd}}-{{HHmmss}}';

        // Try to get the pattern from config
        $config = $this->_getHelper()->getEngineConfigData();
        if (isset($config['indices_pattern'])) {
            $pattern = $config['indices_pattern'];
        }

        // Parse pattern to extract datetime tokens
        $matches = array();
        preg_match_all('/{{([\w]*)}}/', $pattern, $matches);

        foreach (array_combine($matches[0], $matches[1]) as $k => $v) {
            // Replace tokens (UTC date used)
            $pattern = str_replace($k, $currentDate->toString($v), $pattern);
        }

        // Set the new index name
        $this->setIndexName($pattern);

        // Indicates an old index exits
        $this->_oldIndex = true;
        
        return $this;
    }

    /**
     * Install the new index after full reindex
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Client
     */
    public function installNewIndex()
    {
        if ($this->_oldIndex) {
            $config = $this->_getHelper()->getEngineConfigData();
    
            $alias = $config['alias'];
            $this->getIndex($this->_indexName)->addAlias($alias, false);
    
            $status = new \Elastica\Status($this);
            foreach ($status->getIndicesWithAlias($alias) as $index) {
                if ($index->getName() != $this->_indexName) {
                    $index->delete();
                }
            }
        }

        return $this;
    }
    /**
     * Retrieve an index by name
     *
     * @param string|bool $indexName The index name. Default index used if equals to false
     *
     * @return \Elastica\Index
     */
    public function getIndex($indexName = false)
    {
        if ($indexName == false) {
            $indexName = $this->_indexName;
        }

        return parent::getIndex($indexName);
    }

}