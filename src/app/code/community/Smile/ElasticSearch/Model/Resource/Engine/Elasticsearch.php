<?php
/**
 * Elastic search engine.
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
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
{
    const CACHE_INDEX_PROPERTIES_ID = 'elasticsearch_index_properties';
    const DEFAULT_ROWS_LIMIT        = 9999;
    const UNIQUE_KEY                = 'unique';

    /**
     * @var string List of advanced index fields prefix.
     */
    protected $_advancedIndexFieldsPrefix = '#';

    /**
     * @var array List of advanced dynamic index fields.
     */
    protected $_advancedDynamicIndexFields = array(
        '#position_category_',
        '#price_'
    );

    /**
     * @var object Search engine client.
    */
    protected $_client;

    /**
     * @var array List of dates format.
     */
    protected $_dateFormats = array();

    /**
     * @var array List of default query parameters.
    */
    protected $_defaultQueryParams = array(
        'offset' => 0,
        'limit' => 100,
        'sort_by' => array(array('relevance' => 'desc')),
        'store_id' => null,
        'locale_code' => null,
        'fields' => array(),
        'params' => array(),
        'ignore_handler' => false,
        'filters' => array(),
    );

    /**
     * @var array List of indexable attribute parameters.
    */
    protected $_indexableAttributeParams = array();

    /**
     * @var int Last number of results found.
    */
    protected $_lastNumFound;

    /**
     * @var array List of non fulltext fields.
     */
    protected $_notInFulltextField = array(
        self::UNIQUE_KEY,
        'id',
        'store_id',
        'in_stock',
        'categories',
        'show_in_categories',
        'visibility'
    );

    /**
     * @var bool Stores search engine availibility
    */
    protected $_test = null;

    /**
     * @var array List of used fields.
     */
    protected $_usedFields = array(
        self::UNIQUE_KEY,
        'id',
        'sku',
        'price',
        'store_id',
        'categories',
        'show_in_categories',
        'visibility',
        'in_stock',
        'score'
    );



    /**
     * Initializes search engine.
     *
     * @see Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Adapter
     */
    public function __construct()
    {
        $this->_defaultAdapter = Mage::getResourceSingleton('smile_elasticsearch/engine_elasticsearch_adapter');
    }

    /**
     * Cleans caches.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
     */
    public function cleanCache()
    {
        Mage::app()->removeCache(self::CACHE_INDEX_PROPERTIES_ID);

        return $this;
    }

    /**
     * Cleans index.
     *
     * @param int    $storeId Store ind to be cleaned
     * @param int    $id      Document id to be cleaned
     * @param string $type    Document type to be cleaned
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
     */
    public function cleanIndex($storeId = null, $id = null, $type = 'product')
    {
        //$this->getClient()->cleanIndex($storeId, $id, $type);

        return $this;
    }

    /**
     * Deletes index.
     *
     * @return mixed
     */
    public function deleteIndex()
    {
        return $this->getAdapter()->deleteIndex();
    }

    /**
     * Retrieves stats for specified query.
     *
     * @param string $query  Fulltext query
     * @param array  $params Search params (filters, facets, ...)
     * @param string $type   Document type to be searched
     *
     * @return array
     */
    public function getStats($query, $params = array(), $type = 'product')
    {
        $stats = $this->_search($query, $params, $type);

        return isset($stats['facets']['stats']) ? $stats['facets']['stats'] : array();
    }

    /**
     * Saves products data in index.
     *
     * @param int    $storeId Store id
     * @param array  $indexes Documents data
     * @param string $type    Documents type
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
     */
    public function saveEntityIndexes($storeId, $indexes, $type = 'product')
    {
        $indexes = $this->addAdvancedIndex($indexes, $storeId, array_keys($indexes));

        $helper = $this->_getHelper();
        $store = Mage::app()->getStore($storeId);
        $localeCode = $helper->getLocaleCode($store);
        $searchables = $helper->getSearchableAttributes();
        $sortables = $helper->getSortableAttributes();

        foreach ($indexes as &$data) {
            foreach ($data as $key => &$value) {
                if (is_array($value) && strpos($key, 'suggest') !== 0) {
                    $value = array_values(array_filter(array_unique($value)));
                }
                if (array_key_exists($key, $searchables)) {
                    /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
                    $attribute = $searchables[$key];
                    if ($attribute->getBackendType() == 'datetime') {
                        foreach ($value as &$date) {
                            $date = $this->_getDate($store->getId(), $date);
                        }
                        unset($date);
                    } elseif ($attribute->usesSource() && !empty($value)) {
                        if ($attribute->getFrontendInput() == 'multiselect') {
                            $value = explode(',', is_array($value) ? $value[0] : $value);
                        } elseif ($helper->isAttributeUsingOptions($attribute)) {
                            $val = is_array($value) ? $value[0] : $value;
                            if (!isset($data['_options'])) {
                                $data['_options'] = array();
                            }
                            $option = $attribute->setStoreId($storeId)
                                ->getFrontend()
                                ->getOption($val);
                            $data['_options'][] = $option;
                        }
                    }
                }
                if (array_key_exists($key, $sortables)) {
                    $val = is_array($value) ? current($value) : $value;
                    /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
                    $attribute = $sortables[$key];
                    $attribute->setStoreId($store->getId());
                    $key = $helper->getSortableAttributeFieldName($sortables[$key], $localeCode);
                    if ($attribute->usesSource()) {
                        $val = $attribute->getFrontend()->getOption($val);
                    } elseif ($attribute->getBackendType() == 'decimal') {
                        $val = (double) $val;
                    }
                    $data[$key] = $val;
                }
            }
            unset($value);
            $data['store_id'] = $store->getId();
        }
        unset($data);

        $docs = $this->_prepareDocs($indexes, $type, $localeCode);
        $this->_addDocs($docs);

        return $this;
    }

    /**
     * Checks Elasticsearch availability.
     *
     * @return bool
     */
    public function test()
    {
        if (null !== $this->_test) {
            return $this->_test;
        }

        try {
            $this->_test = $this->getAdapter()->getStatus();
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_test = false;
        }

        if ($this->_test === false && $this->_getHelper()->isDebugEnabled()) {
            $this->_getHelper()->showError('Elasticsearch engine is not available');
        }

        return $this->_test;
    }

    /**
     * Adds documents to index.
     *
     * @param array $docs Docuement to be added
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
     *
     * @throws Exception
     */
    protected function _addDocs($docs)
    {
        try {


            if (!empty($docs)) {
                $this->getAdapter()->addDocuments($docs);
            }

        } catch (Exception $e) {
            throw($e);
        }
        $this->getAdapter()->refreshIndex();

        return $this;
    }

    /**
     * Creates and prepares document for indexation.
     *
     * @param int    $entityId Document id
     * @param array  $index    Document data
     * @param string $type     Document type
     *
     * @return mixed
     */
    protected function _createDoc($entityId, $index, $type = 'product')
    {
        return $this->getAdapter()->createDoc($index[self::UNIQUE_KEY], $index, $type);
    }

    /**
     * Escapes specified value.
     *
     * @param string $value Value to be escaped
     *
     * @return mixed
     *
     * @link http://lucene.apache.org/core/3_6_0/queryparsersyntax.html
     */
    protected function _escape($value)
    {
        $result = $value;
        // \ escaping has to be first, otherwise escaped later once again
        $chars = array('\\', '+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '/');

        foreach ($chars as $char) {
            $result = str_replace($char, '\\' . $char, $result);
        }

        return $result;
    }

    /**
     * Escapes specified phrase.
     *
     * @param string $value Value to be escaped
     *
     * @return string
     */
    protected function _escapePhrase($value)
    {
        $pattern = '/("|\\\)/';
        $replace = '\\\$1';

        return preg_replace($pattern, $replace, $value);
    }

    /**
     * Phrases specified value.
     *
     * @param string $value Value to be escaped
     *
     * @return string
     */
    protected function _phrase($value)
    {
        return '"' . $this->_escapePhrase($value) . '"';
    }

    /**
     * Prepares facets conditions.
     *
     * @param array $facetsFields Field to be transform as facets
     *
     * @return array
     */
    protected function _prepareFacetsConditions($facetsFields)
    {
        $result = array();
        if (is_array($facetsFields)) {
            foreach ($facetsFields as $facetField => $facetFieldConditions) {
                if (empty($facetFieldConditions)) {
                    $result['fields'][] = $facetField;
                } else {
                    if (isset($facetFieldConditions['interval'])) {
                        $result['histogram'][$facetField] = $facetFieldConditions['interval'];
                    } else {
                        foreach ($facetFieldConditions as $facetCondition) {
                            if (is_array($facetCondition) && isset($facetCondition['from']) && isset($facetCondition['to'])) {
                                $from = (isset($facetCondition['from']) && strlen(trim($facetCondition['from'])))
                                    ? $this->_prepareQueryText($facetCondition['from'])
                                    : '';
                                $to = (isset($facetCondition['to']) && strlen(trim($facetCondition['to'])))
                                    ? $this->_prepareQueryText($facetCondition['to'])
                                    : '';
                                if (!$from) {
                                    unset($facetCondition['from']);
                                } else {
                                    $facetCondition['from'] = $from;
                                }
                                if (!$to) {
                                    unset($facetCondition['to']);
                                } else {
                                    $facetCondition['to'] = $to;
                                }
                                $result['ranges'][$facetField][] = $facetCondition;
                            } else {
                                $facetCondition = $this->_prepareQueryText($facetCondition);
                                $fieldCondition = $this->_prepareFieldCondition($facetField, $facetCondition);
                                $result['queries'][] = $fieldCondition;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Prepares facets query response.
     *
     * @param array $response Response to be parsed
     *
     * @return array
     */
    protected function _prepareFacetsQueryResponse($response)
    {
        $result = array();
        foreach ($response as $attr => $data) {
            if (isset($data['terms'])) {
                foreach ($data['terms'] as $value) {
                    $result[$attr][$value['term']] = $value['count'];
                }
            } elseif (isset($data['_type']) && $data['_type'] == 'statistical') {
                $result['stats'][$attr] = $data;
            } elseif (isset($data['ranges'])) {
                foreach ($data['ranges'] as $range) {
                    $from = isset($range['from_str']) ? $range['from_str'] : '';
                    $to = isset($range['to_str']) ? $range['to_str'] : '';
                    $result[$attr]["[$from TO $to]"] = $range['total_count'];
                }
            } elseif (isset($data['entries'])) {
                foreach ($data['entries'] as $entry) {
                    $result[$attr][$entry['key']] = $entry['count'];
                }
            } elseif (preg_match('/\(categories:(\d+) OR show_in_categories\:\d+\)/', $attr, $matches)) {
                $result['categories'][$matches[1]] = $data['count'];
            }
        }

        return $result;
    }

    /**
     * Prepares field condition.
     *
     * @param string $field Field name
     * @param string $value Filter value
     *
     * @return string
     */
    protected function _prepareFieldCondition($field, $value)
    {
        if ($field == 'categories') {
            $fieldCondition = "(categories:{$value} OR show_in_categories:{$value})";
        } else {
            $fieldCondition = $field . ': "' . $value . '"';
        }

        return $fieldCondition;
    }

    /**
     * Prepares filter query text.
     *
     * @param string $text Fulltext query
     *
     * @return mixed|string
     */
    protected function _prepareFilterQueryText($text)
    {
        $words = explode(' ', $text);
        if (count($words) > 1) {
            $text = $this->_phrase($text);
        } else {
            $text = $this->_escape($text);
        }

        return $text;
    }

    /**
     * Prepares filters.
     *
     * @param array $filters Filters
     *
     * @return array
     */
    protected function _prepareFilters($filters)
    {
        $result = array();
        if (is_array($filters) && !empty($filters)) {
            foreach ($filters as $field => $value) {
                if (is_array($value)) {
                    if ($field == 'price' || isset($value['from']) || isset($value['to'])) {
                        $from = (isset($value['from']))
                            ? $this->_prepareFilterQueryText($value['from'])
                            : '';
                        $to = (isset($value['to']))
                            ? $this->_prepareFilterQueryText($value['to'])
                            : '';
                        $fieldCondition = "$field:[$from TO $to]";
                    } else {
                        $fieldCondition = array();
                        foreach ($value as $part) {
                            $part = $this->_prepareFilterQueryText($part);
                            $fieldCondition[] = $this->_prepareFieldCondition($field, $part);
                        }
                        $fieldCondition = '(' . implode(' OR ', $fieldCondition) . ')';
                    }
                } else {
                    $value = $this->_prepareFilterQueryText($value);
                    $fieldCondition = $this->_prepareFieldCondition($field, $value);
                }

                $result[$field] = $fieldCondition;
            }
        }

        return $result;
    }

    /**
     * Prepares query response.
     *
     * @param array $response Response to parsed
     *
     * @return array
     */
    protected function _prepareQueryResponse($response)
    {
        $this->_lastNumFound = (int) $response['hits']['total'];
        $result = array();
        foreach ($response['hits']['hits'] as $doc) {
            $result[] = $doc['_source'];
        }
        return $result;
    }

    /**
     * Prepares query text.
     *
     * @param string $text Fulltext query
     *
     * @return string
     */
    protected function _prepareQueryText($text)
    {
        $words = explode(' ', $text);
        if (count($words) > 1) {
            foreach ($words as $key => &$val) {
                if (!empty($val)) {
                    $val = $this->_escape($val);
                } else {
                    unset($words[$key]);
                }
            }
            $text = '(' . implode(' ', $words) . ')';
        } else {
            $text = $this->_escape($text);
        }

        return $text;
    }

    /**
     * Prepares search conditions.
     *
     * @param mixed $query Filltext query
     *
     * @return string
     */
    protected function _prepareSearchConditions($query)
    {
        if (!is_array($query)) {
            $searchConditions = $this->_prepareQueryText($query);
        } else {
            $searchConditions = array();
            foreach ($query as $field => $value) {
                if (is_array($value)) {
                    if ($field == 'price' || isset($value['from']) || isset($value['to'])) {
                        $from = (isset($value['from']) && strlen(trim($value['from'])))
                            ? $this->_prepareQueryText($value['from'])
                            : '';
                        $to = (isset($value['to']) && strlen(trim($value['to'])))
                            ? $this->_prepareQueryText($value['to'])
                            : '';
                        $fieldCondition = "$field:[$from TO $to]";
                    } else {
                        $fieldCondition = array();
                        foreach ($value as $part) {
                            $part = $this->_prepareFilterQueryText($part);
                            $fieldCondition[] = $field .':'. $part;
                        }
                        $fieldCondition = '('. implode(' OR ', $fieldCondition) .')';
                    }
                } else {
                    if ($value != '*') {
                        $value = $this->_prepareQueryText($value);
                    }
                    $fieldCondition = $field .':'. $value;
                }
                $searchConditions[] = $fieldCondition;
            }
            $searchConditions = implode(' AND ', $searchConditions);
        }

        return $searchConditions;
    }

    /**
     * Prepares sort fields.
     *
     * @param array $sortBy Sort conditions
     *
     * @return array
     */
    protected function _prepareSortFields($sortBy)
    {
        $result = array();
        foreach ($sortBy as $sort) {
            $_sort = each($sort);
            $sortField = $_sort['key'];
            $sortType = $_sort['value'];
            if ($sortField == 'relevance') {
                $sortField = '_score';
            } elseif ($sortField == 'position') {
                $sortField = 'position_category_' . Mage::registry('current_category')->getId();
            } elseif ($sortField == 'price') {
                $websiteId = Mage::app()->getStore()->getWebsiteId();
                $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
                $sortField = 'price_'. $customerGroupId .'_'. $websiteId;
            } else {
                $sortField = $this->_getHelper()->getSortableAttributeFieldName($sortField);
            }
            $result[] = array($sortField => trim(strtolower($sortType)));
        }

        return $result;
    }

    /**
     * Performs search and facetting.
     *
     * @param string $query  Fulltext query
     * @param array  $params Search params (facets, filters, ...)
     * @param string $type   Document type to be searched
     *
     * @return array
     */
    protected function _search($query, $params = array(), $type = 'product')
    {
        $searchConditions = $this->_prepareSearchConditions($query);

        $_params = $this->_defaultQueryParams;
        if (is_array($params) && !empty($params)) {
            $_params = array_intersect_key($params, $_params) + array_diff_key($_params, $params);
        }

        $searchParams = array();
        $searchParams['offset'] = isset($_params['offset'])
            ? (int) $_params['offset']
            : 0;
        $searchParams['limit'] = isset($_params['limit'])
            ? (int) $_params['limit']
            : self::DEFAULT_ROWS_LIMIT;

        if (!is_array($_params['params'])) {
            $_params['params'] = array($_params['params']);
        }

        $searchParams['sort'] = $this->_prepareSortFields($_params['sort_by']);

        $useFacetSearch = (isset($params['facets']) && !empty($params['facets']));
        if ($useFacetSearch) {
            $searchParams['facets'] = $this->_prepareFacetsConditions($params['facets']);
        }

        if (!empty($_params['params'])) {
            foreach ($_params['params'] as $name => $value) {
                $searchParams[$name] = $value;
            }
        }

        if ($_params['store_id'] > 0) {
            $_params['filters']['store_id'] = $_params['store_id'];
        }
        if ($type == 'product') {
            if (!Mage::helper('cataloginventory')->isShowOutOfStock()) {
                $_params['filters']['in_stock'] = '1';
            }

            if (!empty($query)) {
                $visibility = Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds();
            } else {
                $visibility = Mage::getSingleton('catalog/product_visibility')->getVisibleInCatalogIds();
            }
            $_params['filters']['visibility'] = $visibility;
        }

        $searchParams['filters'] = $this->_prepareFilters($_params['filters']);

        if (!empty($params['range_filters'])) {
            $searchParams['range_filters'] = $params['range_filters'];
        }

        if (!empty($params['stats'])) {
            $searchParams['stats'] = $params['stats'];
        }

        $query = Mage::getResourceModel('smile_elasticsearch/engine_elasticsearch_query')
            ->setFulltextQuery($searchConditions)
            ->setQueryParams($searchParams)
            ->setType($type);

        $data = $this->getAdapter()->search($query);

        $result = array();

        if (!isset($data['error'])) {
            if (!isset($params['params']['stats']) || $params['params']['stats'] != 'true') {
                $result = array(
                    'docs' => $this->_prepareQueryResponse($data),
                    'total_count' => $data['hits']['total'],
                );
                if ($useFacetSearch && isset($data['facets'])) {
                    $result['facets'] = $this->_prepareFacetsQueryResponse($data['facets']);
                }

                if (isset($data['suggest']) && isset($data['suggest']['spellcheck'])) {
                    $result['is_spellchecked'] = false;
                    foreach ($data['suggest']['spellcheck'] as $term) {
                        if (!empty($term['options'])) {
                            $result['is_spellchecked'] = true;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Run autocomplete for products on the search engigne
     *
     * @param string $text Text to be autocompleted
     *
     * @return array
     */
    public function suggestProduct($text)
    {
        $data  = array();
        $response = $this->getAdapter()->autocompleteProducts($text);
        Mage::log($response);
        if (!isset($response['error']) && isset($response['suggestions'])) {
            $suggestions = current($response['suggestions']);
            foreach ($suggestions['options'] as $suggestion) {
                $data[] = $suggestion;
            }
        }

        return $data;
    }


    /**
     * Prepare a new empty index for full reindex
     *
     * @return void
     */
    public function prepareNewIndex()
    {
        $this->cleanCache();
        $this->getAdapter()->prepareNewIndex();
    }

    /**
     * Installs the new index after full reindex
     *
     * @return void
     */
    public function installNewIndex()
    {
        $this->getAdapter()->installNewIndex();
    }

    /**
     * Get the adapter used to connect ElasticSearch
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Adapter
     */
    public function getAdapter()
    {
        return $this->_defaultAdapter;

    }

    /**
     * Adds advanced index fields to index data.
     *
     * @param array $index      Product data
     * @param int   $storeId    Store id
     * @param array $productIds Product ids
     *
     * @return array
    */
    public function addAdvancedIndex($index, $storeId, $productIds = null)
    {
        return Mage::getResourceSingleton('smile_elasticsearch/engine_index')
        ->addAdvancedIndex($index, $storeId, $productIds);
    }

    /**
     * Returns advanced search results.
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function getAdvancedResultCollection()
    {
        return $this->getResultCollection();
    }

    /**
     * Checks if advanced index is allowed for current search engine.
     *
     * @return bool
     */
    public function allowAdvancedIndex()
    {
        return true;
    }


    /**
     * Returns product visibility ids for search.
     *
     * @see Mage_Catalog_Model_Product_Visibility
     *
     * @return mixed
     */
    public function getAllowedVisibility()
    {
        return Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds();
    }

    /**
     * Returns advanced index fields prefix.
     *
     * @return string
     */
    public function getFieldsPrefix()
    {
        return $this->_advancedIndexFieldsPrefix;
    }

    /**
     * Retrieves product ids for specified query.
     *
     * @param string $query  Fulltext query
     * @param array  $params Search params (filters, facets, ...)
     * @param string $type   Document type to be searched
     *
     * @return array
     */
    public function getIdsByQuery($query, $params = array(), $type = 'product')
    {
        $ids = array();
        $params['fields'] = array('id');
        $resultTmp = $this->search($query, $params, $type);
        if (!empty($resultTmp['docs'])) {
            foreach ($resultTmp['docs'] as $doc) {
                $ids[] = $doc['id'];
            }
        }
        $result = array(
            'ids' => $ids,
            'total_count'     => (isset($resultTmp['total_count'])) ? $resultTmp['total_count'] : null,
            'faceted_data'    => (isset($resultTmp['facets'])) ? $resultTmp['facets'] : array(),
            'is_spellchecked' => (isset($resultTmp['is_spellchecked'])) ? $resultTmp['is_spellchecked'] : false,
        );

        return $result;
    }

    /**
     * Returns resource name.
     *
     * @return string
     */
    public function getResourceName()
    {
        return 'smile_elasticsearch/advanced';
    }

    /**
     * Returns last number of results found.
     *
     * @return int
     */
    public function getLastNumFound()
    {
        return $this->_lastNumFound;
    }

    /**
     * Returns catalog product collection with current search engine set.
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function getResultCollection()
    {
        return Mage::getResourceModel('smile_elasticsearch/catalog_product_collection')->setEngine($this);
    }

    /**
     * Checks if layered navigation is available for current search engine.
     *
     * @return bool
     */
    public function isLayeredNavigationAllowed()
    {
        return true;
    }

    /**
     * Prepares index data.
     * Should be overriden in child classes if needed.
     *
     * @param array  $index     Indexed data
     * @param string $separator Field separator into the index
     *
     * @return array
     */
    public function prepareEntityIndex($index, $separator = null)
    {
        return $this->_getHelper()->prepareIndexData($index, $separator);
    }

    /**
     * Performs search query and facetting.
     *
     * @param string $query  Fulltext query
     * @param array  $params Search params (filters, facets, ...)
     * @param string $type   Document type to be searched
     *
     * @return array
     */
    public function search($query, $params = array(), $type = 'product')
    {
        try {
            Varien_Profiler::start('ELASTICSEARCH');
            $result = $this->_search($query, $params, $type);
            Varien_Profiler::stop('ELASTICSEARCH');
            return $result;
        } catch (Exception $e) {
            Mage::logException($e);
            if ($this->_getHelper()->isDebugEnabled()) {
                $this->_getHelper()->showError($e->getMessage());
            }
        }

        return array();
    }


    /**
     * Transforms specified date to basic YYYY-MM-dd format.
     *
     * @param int    $storeId Current store id
     * @param string $date    Date to be transformed
     *
     * @return null|string
     */
    protected function _getDate($storeId, $date = null)
    {
        if (!isset($this->_dateFormats[$storeId])) {
            $timezone = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $storeId);
            $locale   = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $storeId);
            $locale   = new Zend_Locale($locale);

            $dateObj  = new Zend_Date(null, null, $locale);
            $dateObj->setTimezone($timezone);
            $this->_dateFormats[$storeId] = array($dateObj, $locale->getTranslation(null, 'date', $locale));
        }

        if (is_empty_date($date)) {
            return null;
        }

        list($dateObj, $localeDateFormat) = $this->_dateFormats[$storeId];
        $dateObj->setDate($date, $localeDateFormat);

        return $dateObj->toString('YYYY-MM-dd');
    }

    /**
     * Returns search helper.
     *
     * @return Smile_ElasticSearch_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('smile_elasticsearch');
    }

    /**
     * Returns indexable attribute parameters.
     *
     * @return array
     */
    protected function _getIndexableAttributeParams()
    {
        if (null === $this->_indexableAttributeParams) {
            $this->_indexableAttributeParams = array();
            $attributes = $this->_getHelper()->getSearchableAttributes();
            foreach ($attributes as $attribute) {
                /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
                $this->_indexableAttributeParams[$attribute->getAttributeCode()] = array(
                    'backend_type'   => $attribute->getBackendType(),
                    'frontend_input' => $attribute->getFrontendInput(),
                    'search_weight'  => $attribute->getSearchWeight(),
                    'is_searchable'  => $attribute->getIsSearchable()
                );
            }
        }

        return $this->_indexableAttributeParams;
    }

    /**
     * Returns store locale code.
     *
     * @param int $storeId Store Id
     *
     * @return string
     */
    protected function _getLocaleCode($storeId = null)
    {
        return $this->_getHelper()->getLocaleCode($storeId);
    }

    /**
     * Transforms specified object to an array.
     *
     * @param object $object Source object
     *
     * @return array
     */
    protected function _objectToArray($object)
    {
        if (!is_object($object) && !is_array($object)) {
            return $object;
        }
        if (is_object($object)) {
            $object = get_object_vars($object);
        }

        return array_map(array($this, '_objectToArray'), $object);
    }

    /**
     * Perpare document to be indexed
     *
     * @param array  $docsData   Source document data to be indexed
     * @param string $type       Document type
     * @param string $localeCode Locale indexed
     *
     * @return array
     */
    protected function _prepareDocs($docsData, $type, $localeCode = null)
    {
        if (!is_array($docsData) || empty($docsData)) {
            return array();
        }

        $docs = array();

        foreach ($docsData as $entityId => $index) {
            $index[self::UNIQUE_KEY] = $entityId . '|' . $index['store_id'];
            $index['id'] = $entityId;
            $weight = 1;
            if ($type == 'product') {
                $this->_getSuggestionWeight($index);
            }

            $suggestFieldName = $this->_getHelper()->getSuggestFieldNameByLocaleCode($localeCode);

            if (!isset($index[$suggestFieldName]) && $weight) {

                $input = $index['name'];
                if (isset($index['sku'])) {
                    $input[] = $index['sku'];
                }
                $index[$suggestFieldName] = array(
                    'input'   => $input,
                    'payload' => array('product_id' => $entityId),
                    'weight'  => $weight
                );
            }
            $index = $this->_prepareIndexData($index, $localeCode);
            $docs[] = $this->_createDoc($entityId, $index, $type);
        }

        return $docs;
    }

    /**
     * Indicates if product should be suggested or not
     *
     * @param array $data Product data
     *
     * @return boolean
     */
    protected function _getSuggestionWeight($data)
    {
        $visibilityWeight = array(
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE => 0,
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG  => 1,
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH   => 1,
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH        => 2,
        );

        $result = isset($data['visibility']) && isset($data['status']) ? $visibilityWeight[current($data['visibility'])] : 0;
        $result = current($data['status']) == Mage_Catalog_Model_Product_Status::STATUS_ENABLED ? $result : 0;

        return $result;
    }

    /**
     * Prepares index data before indexation.
     *
     * @param array  $data       Document data
     * @param string $localeCode Current locale
     *
     * @return array
     */
    protected function _prepareIndexData($data, $localeCode = null)
    {
        if (!is_array($data) || empty($data)) {
            return array();
        }

        foreach ($data as $key => $value) {
            if (in_array($key, $this->_usedFields)) {
                continue;
            } elseif ($key == 'options') {
                unset($data[$key]);
                continue;
            }
            $field = $this->_getHelper()->getAttributeFieldName($key, $localeCode);
            $field = str_replace($this->_advancedIndexFieldsPrefix, '', $field);
            if ($field != $key) {
                $data[$field] = $value;
                unset($data[$key]);
            }
        }

        return $data;
    }
}
