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
     * Search engine.
     *
     * @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
     */
    protected $_engine;

    /**
     * Current search query.
     *
     * @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    protected $_searchEngineQuery = null;

    /**
     * Loaded facets.
     *
     * @var array
     */
    protected $_facets = array();

    /**
     * Search entity ids.
     *
     * @var array
     */
    protected $_searchedEntityIds = array();

    /**
     * Sort by definition.
     *
     * @var array
     */
    protected $_sortBy = array();

    /**
     * Count of products by attrubute set.
     *
     * @var array
     */
    protected $_productCountBySetId = null;


    /**
     * Indicates if the collection is spellchecked or not
     *
     * @var boolean
     */
    protected $_isSpellChecked = false;

    /**
     * Stores query text filter.
     *
     * @param string $query Fulltext search query to be applied
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function addSearchFilter($query)
    {
        $this->getSearchEngineQuery()->setFulltextQuery($query);
        return $this;
    }


    /**
     * Returns faceted data.
     *
     * @param string $field Facet to be retrieved
     *
     * @deprecated
     *
     * @return array
     */
    public function getFacetedData($field)
    {
        $facetData = array();

        if (is_null($this->_totalRecords)) {
            $this->getSize();
        }

        $facet = $this->getFacet($field);

        if ($facet) {
            $facetData = $facet->getItems();
        }

        return $facetData;
    }

    /**
     * Return the facet object loaded during the search. False if not exists or not loaded yet.
     *
     * @param string $field Facet to be retrieved
     *
     * @return NULL|Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Facet_Abstract
     */
    public function getFacet($field)
    {
        $facet = null;

        if (is_null($this->_totalRecords)) {
            $this->getSize();
        }

        if (array_key_exists($field, $this->_facets)) {
            $facet = $this->_facets[$field];
        }

        return $facet;
    }

    /**
     * Returns collection size.
     *
     * @return int
     */
    public function getSize()
    {
        if (is_null($this->_totalRecords)) {
            $query = clone $this->getSearchEngineQuery();

            $query->setPageParams(0, 0);

            if ($this->getStoreId()) {
                $store = Mage::app()->getStore($this->getStoreId());
                $this->_searchEngineQuery->setLanguageCode(Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store));
            }

            $query->addFilter('terms', array('store_id' => $this->getStoreId()));

            $result = $query->search();
            $this->_totalRecords = isset($result['total_count']) ? $result['total_count'] : null;
            if (isset($result['facets'])) {
                $this->_facets = array_merge($this->_facets, $result['facets']);
            }

            $this->_isSpellChecked = $query->isSpellchecked();
        }

        return $this->_totalRecords;
    }

    /**
     * Defines current search engine.
     *
     * @param Smile_ElasticSearch_Model_Resource_Engine_ElasticSearch $engine Search engine to be set
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function setEngine(Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch $engine)
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
        $this->_prepareQuery();

        $ids = array();
        if (!empty($this->_facets)) {
            $this->getSearchEngineQuery()->resetFacets();
        }
        $result = $this->getSearchEngineQuery()->search();

        $ids = isset($result['ids']) ? $result['ids'] : array();
        if (isset($result['facets'])) {
            $this->_facets = array_merge($this->_facets, $result['facets']);
        }
        $this->_totalRecords = isset($result['total_count']) ? $result['total_count'] : null;
        $this->_isSpellChecked = $this->getSearchEngineQuery()->isSpellchecked();

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
    protected function _prepareQuery()
    {
        $query = $this->getSearchEngineQuery();

        if (!empty($this->_sortBy)) {
            $query->addSortOrder($this->_sortBy);
        }

        if ($this->_pageSize !== false && $this->_curPage !== false) {
            $query->setPageParams($this->_curPage, $this->_pageSize);
        }

        if ($this->getStoreId()) {
            $query->addFilter('terms', array('store_id' => $this->getStoreId()));
        }
    }

    /**
     * Get the ES query model associated with the product collection.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function getSearchEngineQuery()
    {
        if ($this->_searchEngineQuery === null) {
            $this->_searchEngineQuery = $this->_engine->createQuery('product');

            if ($this->getStoreId()) {
                $store = Mage::app()->getStore($this->getStoreId());
                $this->_searchEngineQuery->setLanguageCode(Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store));
            }
        }

        return $this->_searchEngineQuery;
    }

    /**
     * Indicates if spellchecker the collection has exact matches or not.
     *
     * @return boolean
     */
    public function isSpellchecked()
    {
        return $this->_isSpellChecked;
    }

    /**
     * Get product count by attribute set id.
     *
     * @return array
     */
    public function getProductCountBySetId()
    {
        if ($this->_productCountBySetId == null) {

            $searchQuery = clone $this->getSearchEngineQuery();

            $searchQuery->resetFacets()
                ->setQueryType(null);

            if ($this->getStoreId()) {
                $searchQuery->addFilter('terms', array('store_id' => $this->getStoreId()));
            }

            $facetMaxSize = Mage::getResourceModel('eav/entity_attribute_set_collection')
                ->getSize();

            $options = array('field' => 'attribute_set_id', 'size' => $facetMaxSize);
            $searchQuery->addFacet('attribute_set_id', 'terms', $options);

            $searchQuery->setPageParams(0, 0);
            $response = $searchQuery->search();

            $this->_productCountBySetId = $response['facets']['attribute_set_id']->getItems();
        }

        return $this->_productCountBySetId;
    }

    /**
     * Retrieve unique attribute set ids in collection
     *
     * @return array
     */
    public function getSetIds()
    {
        return array_keys($this->getProductCountBySetId());
    }

}
