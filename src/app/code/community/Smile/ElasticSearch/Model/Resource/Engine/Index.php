<?php
/**
 * Search engine index tool.
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
class Smile_ElasticSearch_Model_Resource_Engine_Index extends Mage_CatalogSearch_Model_Resource_Fulltext
{
    /**
     * Id of the rating used as default for each store.
     *
     * @var array
     */
    protected $_defaultRatingIdByStore = array();

    /**
     * @var Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected  $_categoryNameAttribute = null;

    /**
     * @var Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected $_useNameInSearchAttribute = null;

    /**
     * @var array
     */
    protected  $_categoryNameCache = array();

    /**
     * Adds advanced index data.
     *
     * @param array $index      Data indexed
     * @param int   $storeId    Store id to reindex
     * @param array $productIds Product ids to reindex
     *
     * @return mixed
     */
    public function addAdvancedIndex($index, $storeId, $productIds = null)
    {
        if (is_null($productIds) || !is_array($productIds)) {
            $productIds = array();
            foreach ($index as $entityData) {
                $productIds[] = $entityData['entity_id'];
            }
            $index = array_combine($productIds, $index);
        }

        if (count($productIds)) {
            $categoryData = $this->_getCatalogCategoryData($storeId, $productIds);
            $priceData = $this->_getCatalogProductPriceData(array_keys($categoryData));
            $ratingData = $this->_getRatingData($storeId, array_keys($priceData));

            foreach ($index as $productId => &$productData) {

                if (isset($categoryData[$productId]) && isset($priceData[$productId])) {
                    $productData += $categoryData[$productId];
                    $productData += $priceData[$productId];

                    if (isset($ratingData[$productId])) {
                        $productData += $ratingData[$productId];
                    }

                } else {
                    unset($index[$productId]);
                }
            }
        }

        return $index;
    }


    /**
     * Returns first rating that can be applied for a given store
     *
     * @param int $storeId The store id we want the default rating for
     *
     * @return false|int
     */
    protected function _getDefaultRatingId($storeId)
    {
        if (!isset($this->_defaultRatingIdByStore[$storeId])) {
            if (!Mage::helper('core')->isModuleEnabled('Mage_Rating')) {
                $ratingId = false;
            } else {
                $ratingId = false;
                $ratings = Mage::getResourceModel('rating/rating_collection')
                    ->setStoreFilter($storeId);

                if ($ratings->getSize() > 0) {
                    $ratingId = $ratings->getFirstItem()->getId();
                }
            }
            $this->_defaultRatingIdByStore[$storeId] = $ratingId;
        }

        return $this->_defaultRatingIdByStore[$storeId];
    }

    /**
     * Retrieve product ratings per store for the product list
     *
     * @param int   $storeId    Store id
     * @param array $productIds Product ids
     *
     * @return array
     */
    protected function _getRatingData($storeId, $productIds)
    {
        $result = array();

        if (!empty($productIds)) {
            $adapter = $this->_getWriteAdapter();
            $indexedRatingId = $this->_getDefaultRatingId($storeId);

            if ($indexedRatingId !== false) {

                $select = $adapter->select();

                $select->from(array('r' => $this->getTable('rating/rating_vote_aggregated')))
                    ->where('r.entity_pk_value IN (?)', $productIds)
                    ->where('r.rating_id = ?', (int) $indexedRatingId)
                    ->where('store_id = ?', (int) $storeId);

                foreach ($adapter->fetchAll($select) as $row) {
                    $productId = (int) $row['entity_pk_value'];
                    $result[$productId]['rating_filter'] = (float) $row['percent_approved'];
                }
            }
        }

        return $result;
    }

    /**
     * Retrieves category data for advanced index.
     *
     * @param int   $storeId    Store id to reindex
     * @param array $productIds Product ids to reindex
     * @param bool  $visibility Index product visibiliy into category
     *
     * @return array
     */
    protected function _getCatalogCategoryData($storeId, $productIds, $visibility = true)
    {
        $result = array();
        $loadedCategoryIds = array();

        // Retrieve data from index
        $categoryProductIndexData = $this->_getCategoryProductIndexData($productIds, $storeId);

        foreach ($categoryProductIndexData as $row) {
            $productId  = (int) $row['product_id'];
            $categoryId = (int) $row['category_id'];

            // All categories are now parent category => can be added into 'categories' without particular check
            $result[$productId]['categories'][] = $categoryId;
            $result[$productId]['position'][] = array('category_id' => $categoryId, 'position' => (int) $row['position']);

            // Filling the "show_in_categories" field from the path
            // Possible since all categories are have is_anchor set to true
            $parentCategories = explode('/', $row['path']);
            array_shift($parentCategories);

            $showInCategories = $parentCategories;
            if (isset($result[$productId]['show_in_categories'])) {
                $showInCategories = array_merge($parentCategories, $result[$productId]['show_in_categories']);
            }

            $result[$productId]['show_in_categories'] = array_values(array_unique($showInCategories));

            // Append the category to the category whose name should be loaded
            $loadedCategoryIds = array_merge($loadedCategoryIds, $showInCategories);
        }

        // Append new categories into the cache of names
        $storeCategoryName = array_filter($this->_loadCategoryNames(array_unique($loadedCategoryIds), $storeId));

        foreach ($result as &$categoriesData) {
            // Fill the category_name field from the cache of names
            $categoryIdsAsKeys = array_fill_keys($categoriesData['show_in_categories'], 1);
            $categoriesData['category_name'] = array_values(array_intersect_key($storeCategoryName, $categoryIdsAsKeys));
        }

        return $result;
    }

    /**
     * Select for categories data of some products.
     *
     * @param array $productIds Ids of the products
     * @param int   $storeId    Store Id
     *
     * @return array
     */
    protected function _getCategoryProductIndexData($productIds, $storeId)
    {

        $rootCategoryId = (int) Mage::app()->getStore($storeId)->getRootCategoryId();

        $adapter = $this->_getWriteAdapter();
        $select = $this->_getWriteAdapter()->select()
            ->from(array('cat' => $this->getTable('catalog/category_product_index')))
            ->join(array('e' => $this->getTable('catalog/category')), 'cat.category_id = e.entity_id', array('path' => 'e.path'))
            ->where('cat.product_id IN (?)', $productIds)
            ->where('cat.is_parent = ?', 1)
            ->where('cat.store_id = ?', (int) $storeId);

        return $adapter->fetchAll($select);
    }

    /**
     * Add some categories name into the cache of names of categories.
     *
     * @param array $categoryIds Ids of the categories to be added to the cache.
     * @param int   $storeId     Store Id
     *
     * @return array
     */
    protected function _loadCategoryNames($categoryIds, $storeId)
    {
        $loadCategoryIds = $categoryIds;

        if (isset($this->_categoryNameCache[$storeId])) {
            $loadCategoryIds = array_diff($categoryIds, array_keys($this->_categoryNameCache[$storeId]));
        }

        $loadCategoryIds = array_map('intval', $loadCategoryIds);

        if (!empty($loadCategoryIds)) {

            $rootCategoryId = (int) Mage::app()->getStore($storeId)->getRootCategoryId();
            $this->_categoryNameCache[$storeId][$rootCategoryId] = '';

            $adapter     = $this->_getWriteAdapter();
            $nameAttr    = $this->_getCategoryNameAttribute();
            $useNameAttr = $this->_getUseNameInSearchAttribute();

            $select = $adapter->select()
                ->from(array('default_value' => $nameAttr->getBackendTable()), array('entity_id'))
                ->where('default_value.entity_id != ?', $rootCategoryId)
                ->where('default_value.store_id = ?', 0)
                ->where('default_value.attribute_id = ?', (int) $nameAttr->getAttributeId())
                ->where('default_value.entity_id IN (?)', $loadCategoryIds);

            $joinUseNameCond = sprintf(
                "default_value.entity_id = use_name_default_value.entity_id" .
                " AND use_name_default_value.attribute_id = %d AND use_name_default_value.store_id = %d",
                (int) $useNameAttr->getAttributeId(),
                0
            );
            $select->joinLeft(array('use_name_default_value' => $useNameAttr->getBackendTable()), $joinUseNameCond, array());

            if (Mage::app()->isSingleStoreMode()) {
                $select->columns(array('name' => 'default_value.value'));
                $select->columns(array('use_name' => 'COALESCE(use_name_default_value.value,1)'));
            } else {
                $joinStoreNameCond = sprintf(
                    "default_value.entity_id = store_value.entity_id AND store_value.attribute_id = %d AND store_value.store_id = %d",
                    (int) $nameAttr->getAttributeId(),
                    (int) $storeId
                );
                $select->joinLeft(array('store_value' => $nameAttr->getBackendTable()), $joinStoreNameCond, array())
                    ->columns(array('name' => 'COALESCE(store_value.value,default_value.value)'));

                $joinUseNameStoreCond = sprintf(
                    "default_value.entity_id = use_name_store_value.entity_id" .
                    " AND use_name_store_value.attribute_id = %d AND use_name_store_value.store_id = %d",
                    (int) $useNameAttr->getAttributeId(),
                    (int) $storeId
                );
                $select->joinLeft(array('use_name_store_value' => $useNameAttr->getBackendTable()), $joinUseNameStoreCond, array())
                    ->columns(array('use_name' => 'COALESCE(use_name_store_value.value,use_name_default_value.value,1)'));

            }

            foreach ($adapter->fetchAll($select) as $row) {
                $categoryId = (int) $row['entity_id'];
                $this->_categoryNameCache[$storeId][$categoryId] = '';
                if ((bool) $row['use_name']) {
                    $this->_categoryNameCache[$storeId][$categoryId] = $row['name'];
                }
            }
        }

        return isset($this->_categoryNameCache[$storeId]) ? $this->_categoryNameCache[$storeId] : array();
    }

    /**
     * Retrieves product price data for advanced index.
     *
     * @param array $productIds Product ids to reindex
     *
     * @return array
     */
    protected function _getCatalogProductPriceData($productIds = null)
    {
        $result = array();

        if (!empty($productIds)) {
            $adapter = $this->_getWriteAdapter();

            $select = $adapter->select()
                ->from(
                    $this->getTable('catalog/product_index_price'),
                    array(
                        'entity_id',
                        'customer_group_id',
                        'website_id',
                        'min_price',
                        'has_discount' => new Zend_Db_Expr('COALESCE((price - min_price) > 0, 0)')
                    )
                );

            if ($productIds) {
                $select->where('entity_id IN (?)', $productIds);
            }

            foreach ($adapter->fetchAll($select) as $row) {
                $productId = (int) $row['entity_id'];

                $priceKey = sprintf('price_%s_%s', $row['customer_group_id'], $row['website_id']);
                $result[$productId][$priceKey] = round($row['min_price'], 2);

                $discountKey = sprintf('has_discount_%s_%s', $row['customer_group_id'], $row['website_id']);
                $result[$productId][$discountKey] = (bool) $row['has_discount'];
            }
        }

        return $result;
    }

    /**
     * Returns category name attribute
     *
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected function _getCategoryNameAttribute()
    {
        if ($this->_categoryNameAttribute === null) {
            $this->_categoryNameAttribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_category', 'name');
        }

        return $this->_categoryNameAttribute;
    }

    /**
     * Returns category "use name in product search" attribute
     *
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected function _getUseNameInSearchAttribute()
    {
        if ($this->_useNameInSearchAttribute === null) {
            $this->_useNameInSearchAttribute = Mage::getModel('eav/entity_attribute')
                ->loadByCode('catalog_category', 'used_in_product_search');
        }

        return $this->_useNameInSearchAttribute;
    }
}
