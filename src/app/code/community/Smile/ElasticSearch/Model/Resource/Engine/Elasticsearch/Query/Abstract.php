<?php
/**
 * ElaticSearch query model
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
abstract class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
    extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Abstract
{
    /**
     * @var string
     */
    const DEFAULT_ROWS_LIMIT = 10000;

    /**
     * @var int
     */
    const SORT_ORDER_LAST = PHP_INT_MAX;

    /**
     * Magento query type (eg: product_search_layer).
     *
     * @var string
     */
    protected $_queryType = 'default';


    /**
     * ES query type (eg. product, category, ...)
     *
     * @var string
     */
    protected $_type;

    /**
     * Filter applied to the query.
     *
     * @var array
     */
    protected $_filters = array();

    /**
     * Facets applied to the query.
     *
     * @var array
     */
    protected $_facets = array();

    /**
     * Pagination of the query.
     *
     * @var array
     */
    protected $_page = array('from' => 0, 'size' => self::DEFAULT_ROWS_LIMIT);

    /**
     * Fulltext search query part.
     *
     * @var string
     */
    protected $_fulltextQuery = '';

    /**
     * Sort order of the query
     *
     * @var array
     */
    protected $_sort = array();

    /**
     * Query language code
     *
     * @var string
     */
    protected $_languageCode;

    /**
     * Available facets models
     *
     * @var array
     */
    protected $_facetModelNames = array(
       'terms'      => 'smile_elasticsearch/engine_elasticsearch_query_facet_terms',
       'termsStats' => 'smile_elasticsearch/engine_elasticsearch_query_facet_termsStats',
       'histogram'  => 'smile_elasticsearch/engine_elasticsearch_query_facet_histogram',
       'queryGroup' => 'smile_elasticsearch/engine_elasticsearch_query_facet_queryGroup',
    );

    /**
     * Available filter models
     *
     * @var array
     */
    protected $_filterModelNames = array(
        'terms' => 'smile_elasticsearch/engine_elasticsearch_query_filter_terms',
        'range' => 'smile_elasticsearch/engine_elasticsearch_query_filter_range',
        'query' => 'smile_elasticsearch/engine_elasticsearch_query_filter_queryString'
    );

    /**
     * Indicates if the result have been spellchecked
     *
     * @var bool
     */
    protected $_isSpellChecked = false;

    /**
     * Set types of documents matched by the query.
     *
     * @param string $type Type of documents
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function setType($type)
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * Return the current query language code.
     *
     * @return string
     */
    public function getLanguageCode()
    {
        if ($this->_languageCode == null) {
            $currentStore        = Mage::app()->getStore();
            $this->_languageCode = Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($currentStore);
        }
        return $this->_languageCode;
    }

    /**
     * Set the query language code.
     *
     * @param string $languageCode Language code
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function setLanguageCode($languageCode)
    {
        $this->_languageCode = $languageCode;
        return $this;
    }

    /**
     * Allow to give the query a type.
     * Can be used by observers to know if they should be applied to the query or not.
     *
     * Default query type is equal to default.
     * Layer views change query type to "product_search_layer" and "category_products_layer"
     *
     * @param string $type Type of the query.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function setQueryType($type)
    {
        $this->_queryType = $type;
        return $this;
    }

    /**
     * Return the query type.
     *
     * @return string
     */
    public function getQueryType()
    {
        return $this->_queryType;
    }

    /**
     * Get types of documents matched by the query.
     *
     * @return string
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Run the query against ElasticSearch.
     *
     * @return array
     */
    public function search()
    {
        $result = array();

        Varien_Profiler::start('ES:ASSEMBLE:QUERY');
        $query = $this->_assembleQuery();
        Varien_Profiler::stop('ES:ASSEMBLE:QUERY');

        $eventData = new Varien_Object(array('query' => $query, 'query_type' => $this->getQueryType()));
        Varien_Profiler::start('ES:ASSEMBLE:QUERY:OBSERVERS');
        Mage::dispatchEvent('smile_elasticsearch_query_assembled', array('query_data' => $eventData));
        Varien_Profiler::stop('ES:ASSEMBLE:QUERY:OBSERVERS');
        $query = $eventData->getQuery();
        if ($this->getConfig('enable_debug_mode')) {
            Mage::log(json_encode($query), Zend_Log::DEBUG, 'es-queries.log');
        }

        Varien_Profiler::start('ES:EXECUTE:QUERY');
        $response = $this->getClient()->search($query);
        Varien_Profiler::stop('ES:EXECUTE:QUERY');

        if (!isset($response['error'])) {
            $result = array(
                'total_count'  => $response['hits']['total'],
                'faceted_data' => array(),
                'docs'         => array(),
                'ids'          => array()
            );

            foreach ($response['hits']['hits'] as $doc) {

                $result['docs'][] = $doc['fields'];
                $result['ids'][] = (int) current($doc['fields']['entity_id']);
            }

            if (isset($response['facets'])) {
                foreach ($this->_facets as $facetName => $facetModel) {
                    $currentFacet = clone $facetModel;
                    if ($facetModel->isGroup()) {
                        $currentFacet->setResponse($response['facets']);
                        $result['faceted_data'][$facetName] = $facetModel->getItems($response['facets']);
                    } else if (isset($response['facets'][$facetName])) {
                        $currentFacet->setResponse($response['facets'][$facetName]);
                        $result['faceted_data'][$facetName] = $facetModel->getItems($response['facets'][$facetName]);
                    }
                    $result['facets'][$facetName] = $currentFacet;
                }
            }
        } else {
            Mage::log($response['error'], Zend_Log::ERR, 'search_errors.log');
        }

        return $result;
    }

    /**
     * Set the fulltext query part of the query.
     *
     * @param string $query The fulltext query
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function setFulltextQuery($query)
    {
        $this->_fulltextQuery = $query;
        return $this;
    }

    /**
     * Append a sort order.
     *
     * @param array $sortOrder Sort order definition
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function addSortOrder($sortOrder)
    {
        if (array(is_array($sortOrder)) && is_array(current($sortOrder))) {
            foreach ($sortOrder as $currentSortOrder) {
                $this->addSortOrder($currentSortOrder);
            }
        } else if (is_array($sortOrder)) {
            $this->_sort[] = $sortOrder;
        }
        return $this;
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
     * Prepares filter query text.
     *
     * @param string $text Fulltext query
     *
     * @return mixed|string
     */
    public function prepareFilterQueryText($text)
    {
        $words = explode(' ', $text);
        if (count($words) > 1) {
            $text = $this->_escapePhrase($text);
        } else {
            $text = $this->_escape($text);
        }

        return $text;
    }

    /**
     * Set pagination.
     *
     * @param int $currentPage Current page navigated.
     * @param int $pageSize    Size of a single page.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function setPageParams($currentPage = 0, $pageSize = self::DEFAULT_ROWS_LIMIT)
    {
        $page = ($currentPage  > 0) ? (int) $currentPage  : 1;
        $rowCount = ($pageSize > 0) ? (int) $pageSize : 1;
        $this->_page['from'] = $rowCount * ($page - 1);
        $this->_page['size'] = $pageSize;

        return $this;
    }

    /**
     * Get the ES Query
     *
     * @return array
     */
    public function getRawQuery()
    {
        return $this->_assembleQuery();
    }


    /**
     * Transform the query into an ES syntax compliant array.
     *
     * @return array
     */
    protected function _assembleQuery()
    {
        $query = array('index' => $this->getAdapter()->getCurrentIndex()->getCurrentName(), 'type' => $this->getType());
        $query['body']['query']['filtered']['query']['bool']['must'][] = $this->_prepareFulltextCondition();

        foreach ($this->_facets as $facetName => $facet) {

            $facets = $facet->getFacetQuery();

            if (!$facet->isGroup()) {
                $facets = array($facetName => $facets);
            }

            foreach ($facets as $realFacetName => $facet) {
                foreach ($this->_filters as $filterFacetName => $filters) {
                    $rawFilter = array();

                    foreach ($filters as $filter) {
                        $rawFilter[] = $filter->getFilterQuery();
                    }

                    if ($filterFacetName != $facetName && $filterFacetName != '_none_') {
                        $mustConditions = $rawFilter;
                        if (isset($facet['facet_filter']['bool']['must'])) {
                            $mustConditions = array_merge($facet['facet_filter']['bool']['must'], $rawFilter);
                        }
                        $facet['facet_filter']['bool']['must'] = $mustConditions;
                    }
                }
                $query['body']['facets'][$realFacetName] = $facet;
            }
        }

        foreach ($this->_filters as $facetName => $filters) {
            $rawFilter = array();
            foreach ($filters as $filter) {
                $rawFilter[] = $filter->getFilterQuery();
            }
            if ($facetName == '_none_') {
                if (!isset($query['body']['query']['filtered']['filter']['bool']['must'])) {
                    $query['body']['query']['filtered']['filter']['bool']['must'] = array();
                    $query['body']['query']['filtered']['filter']['bool']['_cache'] = true;
                }
                $mustConditions = array_merge($query['body']['query']['filtered']['filter']['bool']['must'], $rawFilter);
                $query['body']['query']['filtered']['filter']['bool']['must'] = $mustConditions;
            } else {
                if (!isset($query['body']['filter']['bool']['must'])) {
                    $query['body']['filter']['bool']['must'] = array();
                }
                $query['body']['filter']['bool']['must'] = array_merge($query['body']['filter']['bool']['must'], $rawFilter);
            }
        }
        // Patch : score not computed when using another sort order than score
        //         as primary sort order

        if (isset($this->_page['size']) && $this->_page['size'] > 0) {
            $query['body']['fields'] = array('entity_id');
            $query['body']['track_scores'] = true;
            $query['body']['sort'] = $this->_prepareSortCondition();
            $query['body'] = array_merge($query['body'], $this->_page);
        } else {
            $query['body'] = array_merge($query['body'], $this->_page);
        }

        return $query;
    }

    /**
     * Build the sort part of the query.
     *
     * @return array
     */
    protected function _prepareSortCondition()
    {
        $result = array();
        $hasRelevance = false;
        $category = Mage::registry('current_category');

        foreach ($this->_sort as $sort) {
            $_sort = each($sort);
            $sortField = $_sort['key'];
            $sortType = $_sort['value'];
            if ($sortField == 'relevance') {
                $sortField = '_score';
                // Score has to be reversed
                $hasRelevance = true;
            } elseif ($sortField == 'position') {
                if ($category == null) {
                    $sortField = '_score';
                    $sortType = $sortType == 'asc' ? 'desc' : 'asc';
                }
            } elseif ($sortField == 'price') {
                $websiteId = Mage::app()->getStore()->getWebsiteId();
                $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
                $sortField = 'price_'. $customerGroupId .'_'. $websiteId;
            } else {
                $sortField = $this->getMapping()->getFieldName($sortField, $this->getLanguageCode(), 'sort');
            }

            $sortDefinition = array(
                'order' => trim(strtolower($sortType)),
                'missing' => self::SORT_ORDER_LAST - 1,
                'ignore_unmapped' => true
            );

            if ($sortField == 'position' && $category != null) {
                $sortDefinition['nested_path'] = 'category_position';
                $sortDefinition['nested_filter'] = array(
                    'term' => array('category_id' => $category->getId())
                );
            }
            $result[] = array($sortField => $sortDefinition);
        }

        if (!$hasRelevance) {
            // Append relevance has last field if not yet present
            // Allow rescoring methods to be applied
            $result[] = array('_score' => 'desc');
        }

        return $result;
    }

    /**
     * Encode a text to be used into a query.
     *
     * @param string $text Text to be encoded
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
     * Build the fulltext query condition for the query.
     *
     * @return array
     */
    abstract protected function _prepareFulltextCondition();

    /**
     * Retrive mapping for the current query type.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
     */
    public function getMapping()
    {
        return $this->getAdapter()->getCurrentIndex()->getMapping($this->_type);
    }

    /**
     * Retrieves searchable fields according to text query.
     *
     * @param string $searchType Type of search currentlty used.
     * @param string $analyzer   Allow to force the analyzer used for the field (whitespace, ...).
     *
     * @return array
     */
    public function getSearchFields($searchType, $analyzer = null)
    {
        return $this->getMapping()->getSearchFields($this->getLanguageCode(), $searchType, $analyzer);
    }

    /**
     * Add a filter to the query.
     *
     * @param string $modelName Name of the model to be used to create the filter.
     * @param array  $options   Options to be passed to the filter constructor.
     * @param string $facetName Associate the filter to a facet. The filter will not be applied to the facet.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function addFilter($modelName, $options = array(), $facetName = '_none_')
    {
        $modelName = $this->_getFilterModelName($modelName);

        if (!isset($this->_filters)) {
            $this->_filters[$facetName] = array();
        }

        $filter = Mage::getResourceModel($modelName, $options);
        if ($filter) {
            $filter->setQuery($this);
            $this->_filters[$facetName][] = $filter;
        }

        return $this;
    }

    /**
     * Add a facet to the query.
     *
     * @param string $name      Name of the facet.
     * @param string $modelName Name of the model to be used to create the facet.
     * @param array  $options   Options to be passed to the facet constructor.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function addFacet($name, $modelName, $options = array())
    {
        $modelName = $this->_getFacetModelName($modelName);

        $facet = Mage::getResourceModel($modelName, $options);

        if ($facet) {
            $facet->setQuery($this);
            $this->_facets[$name] = $facet;
        }

        return $this;
    }

    /**
     * Try to convert the model name from short name (eg."terms")
     * to the model name (eg. "smile_elasticsearch/engine_elasticsearch_query_facet_terms").
     *
     * If no match is found, return the model name unchanged.
     *
     * @param string $modelName Shortname to be converted.
     *
     * @return string
     */
    protected function _getFacetModelName($modelName)
    {
        if (isset($this->_facetModelNames[$modelName])) {
            $modelName = $this->_facetModelNames[$modelName];
        }
        return $modelName;
    }

    /**
     * Try to convert the model name from short name (eg."terms")
     * to the model name (eg. "smile_elasticsearch/engine_elasticsearch_query_filter_terms").
     *
     * If no match is found, return the model name unchanged.
     *
     * @param string $modelName Shortname to be converted.
     *
     * @return string
     */
    protected function _getFilterModelName($modelName)
    {
        if (isset($this->_filterModelNames[$modelName])) {
            $modelName = $this->_filterModelNames[$modelName];
        }
        return $modelName;
    }


    /**
     * Indicates if the query has been spellchecked or not
     *
     * @return bool
     */
    public function isSpellchecked()
    {
        return $this->_isSpellChecked;
    }

    /**
     * Allow to reset the facet for a query.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function resetFacets()
    {
        $this->_facets = array();
        return $this;
    }
}
