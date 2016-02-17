<?php
/**
 * Virtual categories observer
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
 * @package   Smile_VirtualCategories
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualCategories_Model_Observer
{
    /**
     * Handling rule from the request when saving categories.
     *
     * @param Varien_Event_Observer $observer Event data.
     *
     * @return Smile_VirtualCategories_Model_Observer
     */
    public function prepareCategorySave(Varien_Event_Observer $observer)
    {
        $rule     = $observer->getRequest()->getParam('rule', false);
        $category = $observer->getCategory();
        if ($rule !== false) {
            $ruleInstance = Mage::getModel('smile_virtualcategories/rule')->loadPost($rule);
            $category->setVirtualCategoryRule($ruleInstance);

            $positions = $observer->getRequest()->getParam('virtual_category_position', false);
            if ($positions !== false) {
                $category->setVirtualCategoryProductPositions($positions);
            }
        }

        return $this;
    }

    /**
     * Install category filter into categories
     *
     * @param Varien_Event_Observer $observer Event data.
     *
     * @return Smile_VirtualCategories_Model_Observer
     */
    public function prepareCategoryFilter(Varien_Event_Observer $observer)
    {
        // Retrieve filter and category from event
        $filter   = $observer->getFilter();
        $category = $observer->getCategory();

        // Retrieve query associated with the filter
        /** @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Fulltext $query */
        $query = $filter->getLayer()->getProductCollection()->getSearchEngineQuery();

        // Append the query string for the virtual categories
        $queryString = $this->_getVirtualRule($category)->getSearchQuery();
        $query->addFilter('query', array('query_string' => $queryString));

        // Mark filter as installed (avoid default filter behavior)
        $filter->setProductCollectionFilterSet(true);

        return $this;
    }

    /**
     * Install category facet into categories
     *
     * @param Varien_Event_Observer $observer Event data.
     *
     * @return Smile_VirtualCategories_Model_Observer
     */
    public function prepareCategoryFacet(Varien_Event_Observer $observer)
    {
        // Retrieve filter and category from event
        $filter   = $observer->getFilter();
        $category = $observer->getCategory();

        // Retrieve query associated with the filter
        $query = $filter->getLayer()->getProductCollection()->getSearchEngineQuery();

        // Prepare facet query group
        $queries = $this->_getVirtualRule($category)->getChildrenCategoryQueries(array(), false, 1);
        $options = array('queries' => $queries, 'prefix' => 'categories_');
        $query->addFacet('categories', 'queryGroup', $options);
        $filter->setProductCollectionFacetSet(true);

        return $this;
    }

    /**
     * Force the virtual rule to be loaded for a category.
     *
     * @param Mage_Catalog_Model_Category $category The category.
     *
     * @return Smile_VirtualCategories_Model_Rule
     */
    protected function _getVirtualRule($category)
    {
        return Mage::helper('smile_virtualcategories')->getVirtualRule($category);
    }

    /**
     * Append products positions to the current virtual category if needed
     *
     * @param Varien_Event_Observer $observer The observer
     *
     * @event catalogsearch_query_save_after
     *
     * @return Smile_VirtualCategories_Model_Observer
     */
    public function saveProductsPositions(Varien_Event_Observer $observer)
    {
        $category  = $observer->getEvent()->getCategory();
        $positions = $category->getVirtualCategoryProductPositions();

        if (!is_array($positions)) {
            $positions = array();
        }

        $filteredPositions = array_filter($positions, 'is_numeric');
        $resourceModel     = Mage::getResourceModel("smile_virtualcategories/catalog_virtualCategory_product_position");
        $previousProducts  = $resourceModel->getProductIdsByCategory($category);

        $resourceModel->saveProductsPositions($filteredPositions, $category);

        $productIdsToReindex = array_unique(array_merge($previousProducts, array_keys($filteredPositions)));

        if (empty($productIdsToReindex)) {
            return $this;
        }

        // If Enterprise version, Mview index will handle editing, otherwise, process reindex
        if (!Mage::helper("smile_elasticsearch")->isEnterpriseSupportEnabled()) {

            Mage::getSingleton('index/indexer')->processEntityAction(
                $category->setVirtualProductIds($productIdsToReindex),
                Mage_Catalog_Model_Category::ENTITY,
                Mage_Index_Model_Event::TYPE_SAVE
            );

        } else {

            $helper = Mage::helper('smile_virtualcategories/index');

            if ($helper->isLiveProductPositionInVirtualCategoriesReindexEnabled()) {

                $client = Mage::getModel('enterprise_mview/client');
                $client->init(
                    Mage::helper('enterprise_index')->getIndexerConfigValue('virtual_categories_product_pos', 'index_table')
                );
                $arguments = array('value' => $productIdsToReindex);
                $client->execute('smile_virtualcategories/index_action_virtualCategories_product_position_refresh_row', $arguments);

            }
        }
        return $this;
    }

    /**
     * Append a sort by our custom positions when viewing a virtual category
     *
     * @param Varien_Event_Observer $observer The observer
     *
     * @event smile_elasticsearch_query_assembled
     *
     * @return Smile_VirtualCategories_Model_Observer self reference
     */
    public function applyProductsPositions(Varien_Event_Observer $observer)
    {
        $data  = $observer->getQueryData();
        $query = $data->getQuery();

        $category = Mage::registry("current_category");

        if (($category !== null) && $category->getId() && ($this->_getVirtualRule($category) !== null)) {

            $optimizer = Mage::getModel("smile_virtualcategories/virtualCategory_product_position");
            $query     = $optimizer->applyCustomProductsPositions($query, $category);

            $data->setQuery($query);
        }

        return $this;
    }
}

