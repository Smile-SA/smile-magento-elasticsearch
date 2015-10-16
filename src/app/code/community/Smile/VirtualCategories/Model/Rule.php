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
     * Local cache for queries.
     *
     * @var array
     */
    private $_queryCache = array();

    /**
     * Categories already used into query generation. Avoid infinte loop.
     *
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

        $cacheData = $this->getQueryFromCache($category->getId() . $category->getStoreId());
        $query = '';

        if (!$cacheData || (!empty($excludedCategories))) {
            $this->_usedCategories = array();
            $this->addUsedCategoryIds($category->getId());
            if ($category->getIsVirtual()) {
                $this->getConditions()->setRule($this);
                $query = $this->getConditions()->getSearchQuery($excludedCategories);
            } else {
                $query = '(categories:' . $category->getId() . ') OR (show_in_categories:' . $category->getId() . ')';
                $childrenQueries = $this->getChildrenCategoryQueries($excludedCategories, true);
                $query = implode(' OR ', array_merge(array($query), $childrenQueries));
            }

            if (empty($excludedCategories)) {
                $this->cacheQuery($category->getId() . $category->getStoreId(), array($query, $this->_usedCategories));
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
     * @param array $excludedCategories Indicates if some categories should be excluded (avoid infinite loops).
     * @param bool  $onlyVirtual        Indicates if you want to fetch only rules for children that are virtual categories.
     * @param bool  $depth              Indicates if you want to fetch only rules for children with a max depth.
     *
     * @return array
     */
    public function getChildrenCategoryQueries($excludedCategories = array(), $onlyVirtual = false, $depth = false)
    {
        $queries = array();

        $rootCategory = $this->getCategory();
        $categories = Mage::getResourceModel('smile_virtualcategories/catalog_virtualCategory_collection')
            ->setStore($rootCategory->getStoreId())
            ->addIsActiveFilter()
            ->addFieldToFilter('path', array('like' => $rootCategory->getPath() . '/%'))
            ->addAttributeToSelect('virtual_category');

        if (!empty($excludedCategories)) {
            $categories->addFieldToFilter('entity_id', array('nin' => $excludedCategories));
        }

        if ($depth !== false) {
            $categories->addFieldToFilter('level', $rootCategory->getLevel() + 1);
        }

        foreach ($categories as $currentCategory) {
            if ($currentCategory->getIsVirtual() || ($onlyVirtual == false)) {
                $virtualRule = $currentCategory->getVirtualRule();
                $virtualRule->setStoreId($rootCategory->getStoreId());
                $query = $virtualRule->getSearchQuery($excludedCategories);
                if ($query) {
                    $queries[$currentCategory->getId()] = '(' . $query . ')';
                    $this->addUsedCategoryIds($virtualRule->getUsedCategoryIds());
                }
            }
        }

        return array_filter($queries);
    }

    /**
     * Update the rule current store from the store id.
     *
     * @param int $storeId The store id to be set.
     *
     * @return Smile_VirtualCategories_Model_Rule Self Reference.
     */
    public function setStoreId($storeId)
    {
        $this->setData('store', Mage::app()->getStore($storeId));
        $this->setData('store_id', $storeId);
        return $this;
    }

    /**
     * Update the rule current store from the store.
     *
     * @param Mage_Core_Model_Store $store Store to be set.
     *
     * @return Smile_VirtualCategories_Model_Rule Self Reference.
     */
    public function setStore($store)
    {
        $this->setData('store', $store);
        $this->setData('store_id', $store->getId());
        return $this;
    }

    /**
     * Retrieve rule current store.
     *
     * @return Mage_Core_Model_Store
     */
    public function getStore()
    {
        $store = Mage::app()->getStore();
        if ($this->getCategory() && $this->getCategory()->getStoreId()) {
            $storeId = $this->getCategory()->getStoreId();
            $store = Mage::app()->getStore($storeId);
        }
        if ($this->getData('store')) {
            $store = $this->getData('store');
        }
        if ($this->getData('store_id')) {
            $storeId = $this->getData('store_id');
            $store = Mage::app()->getStore($storeId);
        }
        return $store;
    }

    /**
     * Retrieve rule current store id.
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->getStore()->getId();
    }
}
