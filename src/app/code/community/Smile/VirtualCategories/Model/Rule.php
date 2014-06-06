<?php
/**
 * Virtual categories rule
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
class Smile_VirtualCategories_Model_Rule extends Mage_Rule_Model_Rule
{
    /**
     * @var array
     */
    private $_queryCache = array();

    /**
     * Getter for rule conditions collection
     *
     * @return Mage_CatalogRule_Model_Rule_Condition_Combine
     */
    public function getConditionsInstance()
    {
        return Mage::getModel('smile_virtualcategories/rule_condition_combine');
    }

    /**
     * Local caching of queries. Used when a category query is retrieved several times during the same request.
     *
     * @param int $categoryId Id of the category.
     *
     * @return NULL|string
     */
    public function getQueryFromCache($categoryId)
    {
        $cacheInstance = Mage::getSingleton('smile_virtualcategories/rule');
        return isset($cacheInstance->_queryCache[$categoryId]) ? $cacheInstance->_queryCache[$categoryId] : null;
    }

    /**
     * Store category query into the local cache.
     *
     * @param int    $categoryId Id of the category.
     * @param string $query      Query of the category.
     *
     * @return Smile_VirtualCategories_Model_Rule
     */
    public function cacheQuery($categoryId, $query)
    {
        $cacheInstance = Mage::getSingleton('smile_virtualcategories/rule');
        $cacheInstance->_queryCache[$categoryId] = $query;
        return $this;
    }

    /**
     * Build product filter for a category.
     *
     * @param array $excludedCategories Indicates if some categories should be excluded (avoid infinite loops)
     *
     * @return string
     */
    public function getSearchQuery($excludedCategories = array())
    {
        $category = $this->getCategory();

        $query = $this->getQueryFromCache($category->getId());

        if ($query === null) {
            if ($category->getIsVirtual()) {
                $query = $this->getConditions()->getSearchQuery($excludedCategories);
            } else {
                $query = array('categories:' . $category->getId());
                $childrenQueries = $this->getChildrenCategoryQueries($excludedCategories);
                $query = implode(' OR ', array_merge($query, $childrenQueries));
            }

            $this->cacheQuery($category->getId(), $query);
        }

        return $query;
    }

    /**
     * Get all ES queries for children categories of the current categories :
     *
     * - Used to build category facet
     * - Compute inhereted products queries
     *
     * @param array $excludedCategories Indicates if some categories should be excluded (avoid infinite loops)
     *
     * @return array
     */
    public function getChildrenCategoryQueries($excludedCategories = array())
    {
        $queries = array();

        $rootCategory = $this->getCategory();
        $childrenIds  = explode(',', $rootCategory->getChildren());
        $childrenIds  = array_diff($childrenIds, $excludedCategories);

        $virtualCategoryBackendModel = Mage::getModel('smile_virtualcategories/category_attributes_backend_virtual');

        $categories = Mage::getResourceModel('catalog/category_collection')
            ->setStoreId($rootCategory->getId())
            ->addIsActiveFilter()
            ->addIdFilter($childrenIds)
            ->addAttributeToSelect('virtual_category');

        foreach ($categories as $currentCategory) {

            $virtualCategoryBackendModel->afterLoad($currentCategory);

            $query = $currentCategory->getVirtualRule()->getSearchQuery($excludedCategories);
            if ($query) {
                $queries[$currentCategory->getId()] = '(' . $query . ')';
            }
        }

        return array_filter($queries);
    }
}