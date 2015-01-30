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
        $query = $filter->getLayer()->getProductCollection()->getSearchEngineQuery();

        // Append the query string for the virtual categories
        $qs = $category->getVirtualRule()->getSearchQuery();
        $query->addFilter('query', array('query_string' => $qs));

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
        $queries = $category->getVirtualRule()->getChildrenCategoryQueries();
        $options = array('queries' => $queries, 'prefix' => 'categories_');
        $query->addFacet('categories', 'queryGroup', $options);

        $filter->setProductCollectionFacetSet(true);

        return $this;
    }
}

