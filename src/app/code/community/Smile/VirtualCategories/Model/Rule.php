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
     *
     */
    const CACHE_KEY_PREFIX = 'SMILE_VIRTUALCATEGORIES_RULES';

    /**
     * @var array
     */
    private $_queryCache = array();

    /**
     * @var array
     */
    private $_usedCategories = array();

    /**
     * Retrieve list of the category ids used to build the condition
     *
     *  @return array
     */
    public function getUsedCategoryIds()
    {
        return $this->_usedCategories;
    }

    /**
     * Append category id(s) to the list of categories used to build the condition.
     *
     * @param array|int $categoryIds Category to add.
     *
     * @return Smile_VirtualCategories_Model_Rule
     */
    public function addUsedCategoryIds($categoryIds)
    {
        if (!is_array($categoryIds)) {
            $categoryIds = array($categoryIds);
        }

        $categoryIds = array_filter($categoryIds);

        $this->_usedCategories = array_unique(array_merge($this->_usedCategories, $categoryIds));

        return $this;
    }

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
        $data = false;

        if (isset($cacheInstance->_queryCache[$categoryId])) {
            $data = $cacheInstance->_queryCache[$categoryId];
        }

        if ($data === false && $cacheData = Mage::app()->loadCache(self::CACHE_KEY_PREFIX . '_' .$categoryId)) {
            $data = unserialize($cacheData);
        }

        return $data;
    }

    /**
     * Store category query into the local cache.
     *
     * @param int    $categoryId Id of the category.
     * @param string $data       Data to cache [query, used_categories].
     *
     * @return Smile_VirtualCategories_Model_Rule
     */
    public function cacheQuery($categoryId, $data)
    {
        $cacheInstance = Mage::getSingleton('smile_virtualcategories/rule');
        $cacheInstance->_queryCache[$categoryId] = $data;

        $cacheTags = array();
        foreach ($data[1] as $usedCategoryId) {
            $cacheTags[] = Mage_Catalog_Model_Category::CACHE_TAG . '_' . $usedCategoryId;
        }

        $cacheId = self::CACHE_KEY_PREFIX . '_' .$categoryId;

        Mage::app()->saveCache(serialize($data), $cacheId, $cacheTags, Mage_Core_Model_Cache::DEFAULT_LIFETIME);

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

        $cacheData = $this->getQueryFromCache($category->getId());
        $query = '';

        if (!$cacheData || (!empty($excludedCategories))) {

            $this->_usedCategories = array();
            $this->addUsedCategoryIds($category->getId());
            if ($category->getIsVirtual()) {
                $query = $this->getConditions()->getSearchQuery($excludedCategories);
            } else {
                $query = array('categories:' . $category->getId());
                $childrenQueries = $this->getChildrenCategoryQueries($excludedCategories);
                $query = implode(' OR ', array_merge($query, $childrenQueries));
            }
            if (empty($excludedCategories)) {
                $this->cacheQuery($category->getId(), array($query, $this->_usedCategories));
            }
        } else {
            list($query, $this->_usedCategories) = $cacheData;
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
            ->addAttributeToSelect(array('name', 'virtual_category'));

        foreach ($categories as $currentCategory) {
            $virtualCategoryBackendModel->afterLoad($currentCategory);
            $virtualRule = $currentCategory->getVirtualRule();
            $query = $virtualRule->getSearchQuery($excludedCategories);
            if ($query) {
                $queries[$currentCategory->getId()] = '(' . $query . ')';
                $this->addUsedCategoryIds($virtualRule->getUsedCategoryIds());
            }
        }

        return array_filter($queries);
    }
}