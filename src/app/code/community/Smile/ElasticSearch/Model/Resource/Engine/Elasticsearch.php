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
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch extends Smile_ElasticSearch_Model_Resource_Engine_Abstract
{
    const CACHE_INDEX_PROPERTIES_ID = 'elasticsearch_index_properties';

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
                if (is_array($value)) {
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
                    $val = is_array($value) ? $value[0] : $value;
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
        $pattern = '/(\+|-|&&|\|\||!|\(|\)|\{|}|\[|]|\^|"|~|\*|\?|:|\\\)/';
        $replace = '\\\$1';

        return preg_replace($pattern, $replace, $value);
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
     * Returns search helper.
     *
     * @return Smile_ElasticSearch_Helper_Elasticsearch
     */
    protected function _getHelper()
    {
        return Mage::helper('smile_elasticsearch/elasticsearch');
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

                $result[] = $fieldCondition;
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

        $searchParams['filters'] = implode(' AND ', $this->_prepareFilters($_params['filters']));

        if (!empty($params['range_filters'])) {
            $searchParams['range_filters'] = $params['range_filters'];
        }

        if (!empty($params['stats'])) {
            $searchParams['stats'] = $params['stats'];
        }

        $data = $this->getAdapter()->search($searchConditions, $searchParams, $type);

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

}
