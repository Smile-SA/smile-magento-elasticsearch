<?php
/**
 * Category autocomplete collection
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
class Smile_ElasticSearch_Model_Resource_Catalog_Category_Suggest_Collection
   extends Mage_Catalog_Model_Resource_Category_Collection
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
     * @var array Faceted data.
     */
    protected $_facetedData = array();


    /**
     * @var array Search entity ids.
     */
    protected $_searchedEntityIds = array();

    /**
     * @var array Sort by definition.
     */
    protected $_sortBy = array();


    /**
     * Add some fields to filter.
     *
     * @param array $fields Field to be filtered and filter values
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Category_Collection
     */
    public function addFieldsToFilter($fields)
    {
        return $this;
    }

    /**
     * Stores query text filter.
     *
     * @param string $query Fulltext search query to be applied
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Category_Collection
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
            $this->_beforeLoad();
        }

        return $this->_totalRecords;
    }

    /**
     * Defines current search engine.
     *
     * @param Smile_ElasticSearch_Model_Resource_Engine_ElasticSearch $engine Search engine to be set
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Category_Collection
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
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Category_Collection
     */
    public function setOrder($attribute, $dir = self::SORT_ORDER_DESC)
    {
        $this->_sortBy[] = array($attribute => $dir);

        return $this;
    }

    /**
     * Reorder collection according to current sort order.
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Category_Collection
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
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    protected function _beforeLoad()
    {
        $this->_prepareQuery();

        $ids = array();
        $result = $this->getSearchEngineQuery()->search();
        $ids = isset($result['ids']) ? $result['ids'] : array();
        $this->_facetedData = isset($result['faceted_data']) ? $result['faceted_data'] : array();
        $this->_totalRecords = isset($result['total_count']) ? $result['total_count'] : null;
        $this->_isSpellChecked = isset($result['is_spellchecked']) ? $result['is_spellchecked'] : false;

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
     * Get the ES query model associated with the category collection.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function getSearchEngineQuery()
    {
        if ($this->_searchEngineQuery === null) {
            $this->_searchEngineQuery = $this->_engine->createQuery(
                'category',
                'smile_elasticsearch/engine_elasticsearch_query_autocomplete'
            );

            if ($this->getStoreId()) {
                $store = Mage::app()->getStore();
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
        return $this->getSearchEngineQuery()->isSpellchecked();
    }
}