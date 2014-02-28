<?php
/**
 * Custom catalog product collection model handling filtering and facetting through ElasticSearch.
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
class Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection extends Mage_Catalog_Model_Resource_Product_Collection
{
    /**
     * @var Smile_ElasticSearch_Model_Resource_Engine_Abstract Search engine.
     */
    protected $_engine;

    /**
     * @var array Faceted data.
     */
    protected $_facetedData = array();

    /**
     * @var array Facets conditions.
     */
    protected $_facetsConditions = array();

    /**
     * @var array General default query.
     */
    protected $_generalDefaultQuery = array('*' => '*');

    /**
     * @var string Search query text.
     */
    protected $_searchQueryText = '';

    /**
     * @var array Search query filters.
     */
    protected $_searchQueryFilters = array();

    /**
     * @var array Search query range filters.
     */
    protected $_searchQueryRangeFilters = array();

    /**
     * @var array Search entity ids.
     */
    protected $_searchedEntityIds = array();

    /**
     * @var array Sort by definition.
     */
    protected $_sortBy = array();

    /**
     * @var bool Indicates if the collection has been spellchecked or not
     */
    protected $_isSpellChecked = false;
    
    /**
     * Adds facet condition to current collection.
     *
     * @param string $field     Field to be build facet for
     * @param mixed  $condition Facet condition
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function addFacetCondition($field, $condition = null)
    {
        if (array_key_exists($field, $this->_facetsConditions)) {
            if (!empty($this->_facetsConditions[$field])) {
                $this->_facetsConditions[$field] = array($this->_facetsConditions[$field]);
            }
            $this->_facetsConditions[$field][] = $condition;
        } else {
            $this->_facetsConditions[$field] = $condition;
        }

        return $this;
    }

    /**
     * Add some fields to filter.
     *
     * @param array $fields Field to be filtered and filter values
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function addFieldsToFilter($fields)
    {
        return $this;
    }

    /**
     * Stores filter query.
     *
     * @param array $params Param of the filter
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function addFqFilter($params)
    {
        if (is_array($params)) {
            foreach ($params as $field => $value) {
                $this->_searchQueryFilters[$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Stores range filter query.
     *
     * @param array $params Range filter params
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function addFqRangeFilter($params)
    {
        if (is_array($params)) {
            foreach ($params as $field => $value) {
                $this->_searchQueryRangeFilters[$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Stores query text filter.
     *
     * @param string $query Fulltext search query to be applied
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function addSearchFilter($query)
    {
        $this->_searchQueryText = $query;

        return $this;
    }

    /**
     * Stores search query filter.
     *
     * @param mixed $param Field to be filtered
     * @param null  $value Filter value
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function addSearchQfFilter($param, $value = null)
    {
        if (is_array($param)) {
            foreach ($param as $field => $value) {
                $this->addSearchQfFilter($field, $value);
            }
        } elseif (isset($value)) {
            if (isset($this->_searchQueryFilters[$param]) && !is_array($this->_searchQueryFilters[$param])) {
                $this->_searchQueryFilters[$param] = array($this->_searchQueryFilters[$param]);
                $this->_searchQueryFilters[$param][] = $value;
            } else {
                $this->_searchQueryFilters[$param] = $value;
            }
        }

        return $this;
    }

    /**
     * Aggregates search query filters.
     *
     * @return array
     */
    public function getExtendedSearchParams()
    {
        $result = $this->_searchQueryFilters;
        $result['query_text'] = $this->_searchQueryText;

        return $result;
    }

    /**
     * Returns faceted data.
     *
     * @param string $field Facet to be retrieved
     *
     * @return array
     */
    public function getFacetedData($field)
    {
        if (array_key_exists($field, $this->_facetedData)) {
            return $this->_facetedData[$field];
        }

        return array();
    }

    /**
     * Returns collection size.
     *
     * @return int
     */
    public function getSize()
    {
        if (is_null($this->_totalRecords)) {
            $query = $this->_getQuery();
            $params = $this->_getParams();
            $params['limit'] = 1;
            $result = $this->_engine->getIdsByQuery($query, $params);
            $this->_totalRecords = $this->_engine->getLastNumFound();
            $this->_isSpellChecked = isset($result['is_spellchecked']) ? $result['is_spellchecked'] : false;
        }

        return $this->_totalRecords;
    }

    /**
     * Indicates if the spellchecker has been used to process the query
     * 
     * @return boolean
     */
    public function isSpellchecked()
    {
        return $this->_isSpellChecked;
    }
    
    /**
     * Retrieves current collection stats.
     * Used for max price.
     *
     * @param array $fields Stats fields to be collected
     *
     * @return mixed
     */
    public function getStats($fields)
    {
        $query = $this->_getQuery();
        $params = $this->_getParams();
        $params['limit'] = 0;

        if (!is_array($fields)) {
            $fields = array($fields);
        }
        foreach ($fields as $field) {
            $params['stats']['fields'][] = $field;
        }

        $this->_pageSize = false;

        return $this->_engine->getStats($query, $params);
    }

    /**
     * Defines current search engine.
     *
     * @param Smile_ElasticSearch_Model_Resource_Engine_Abstract $engine Search engine to be set
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function setEngine(Smile_ElasticSearch_Model_Resource_Engine_Abstract $engine)
    {
        $this->_engine = $engine;

        return $this;
    }

    /**
     * Stores sort order.
     *
     * @param string $attribute Attribute name to sort by
     * @param string $dir       Sort direction
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function setOrder($attribute, $dir = self::SORT_ORDER_DESC)
    {
        $this->_sortBy[] = array($attribute => $dir);

        return $this;
    }

    /**
     * Reorder collection according to current sort order.
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();
        if (!empty($this->_searchedEntityIds)) {
            $sortedItems = array();
            foreach ($this->_searchedEntityIds as $id) {
                if (isset($this->_items[$id])) {
                    $sortedItems[$id] = $this->_items[$id];
                }
            }
            $this->_items = &$sortedItems;
        }

        return $this;
    }

    /**
     * Handles collection filtering by ids retrieves from search engine.
     * Will also stores faceted data and total records.
     *
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    protected function _beforeLoad()
    {
        $ids = array();
        if ($this->_engine) {
            $result = $this->_engine->getIdsByQuery($this->_getQuery(), $this->_getParams());
            $ids = isset($result['ids']) ? $result['ids'] : array();
            $this->_facetedData = isset($result['faceted_data']) ? $result['faceted_data'] : array();
            $this->_totalRecords = isset($result['total_count']) ? $result['total_count'] : null;
            $this->_isSpellChecked = isset($result['is_spellchecked']) ? $result['is_spellchecked'] : false;
        }

        if (empty($ids)) {
            $ids = array(0); // Fix for no result
        }
        $this->addIdFilter($ids);
        $this->_searchedEntityIds = $ids;
        $this->_pageSize = false;

        return parent::_beforeLoad();
    }

    /**
     * Retrieves parameters.
     *
     * @return array
     */
    protected function _getParams()
    {
        $store = Mage::app()->getStore($this->getStoreId());
        $params = array();
        $params['locale_code'] = $store->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE);
        $params['filters'] = $this->_searchQueryFilters;
        $params['range_filters'] = $this->_searchQueryRangeFilters;

        if (!empty($this->_sortBy)) {
            $params['sort_by'] = $this->_sortBy;
        }

        if ($this->_pageSize !== false) {
            $page = ($this->_curPage  > 0) ? (int) $this->_curPage  : 1;
            $rowCount = ($this->_pageSize > 0) ? (int) $this->_pageSize : 1;
            $params['offset'] = $rowCount * ($page - 1);
            $params['limit'] = $rowCount;
        }

        if (!empty($this->_facetsConditions)) {
            $params['facets'] = $this->_facetsConditions;
        }

        return $params;
    }

    /**
     * Returns stored text query.
     *
     * @return string
     */
    protected function _getQuery()
    {
        return $this->_searchQueryText;
    }
}
