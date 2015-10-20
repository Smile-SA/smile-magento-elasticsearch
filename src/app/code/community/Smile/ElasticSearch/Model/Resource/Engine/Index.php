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
            $productIds = array_column($index, 'entity_id');
            $index = array_combine($productIds, $index);
        }
        if (count($productIds)) {
            $categoryData = $this->_getCatalogCategoryData($storeId, $productIds);
            $priceData = $this->_getCatalogProductPriceData($productIds);

            $ratingData = $this->_getRatingData($storeId, $productIds);

            foreach ($index as $productId => &$productData) {
                if (isset($ratingData[$productId])) {
                    $productData += $ratingData[$productId];
                }

                if (isset($categoryData[$productId]) && isset($priceData[$productId])) {
                    $productData += $categoryData[$productId];
                    $productData += $priceData[$productId];
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
        if (!Mage::helper('core')->isModuleEnabled('Mage_Rating')) {
            $ratingId = false;
        } else if (!isset($this->_defaultRatingIdByStore[$storeId])) {
            $ratingId = false;
            $ratings = Mage::getResourceModel('rating/rating_collection')
                ->setStoreFilter($storeId);

            if ($ratings->getSize() > 0) {
                $ratingId = $ratings->getFirstItem()->getId();
            }
        }

        $this->_defaultRatingIdByStore[$storeId] = $ratingId;

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
        $adapter = $this->_getWriteAdapter();
        $indexedRatingId = $this->_getDefaultRatingId($storeId);

        $result = array();
        if ($indexedRatingId !== false) {

            $select = $adapter->select();

            $select->from(array('r' => $this->getTable('rating/rating_vote_aggregated')))
                ->where('r.entity_pk_value IN (?)', $productIds)
                ->where('r.rating_id = ?', $indexedRatingId)
                ->where('store_id = ?', $storeId);

            foreach ($adapter->fetchAll($select) as $row) {
                $productId = $row['entity_pk_value'];
                if (!isset($result[$productId])) {
                    $result[$productId] = array();
                }
                $result[$productId]['rating_filter'] = (float) $row['percent'];
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
        $adapter = $this->_getWriteAdapter();

        $columns = array('product_id' => 'cat.product_id');

        if ($visibility) {
            $columns[] = 'visibility';
        }

        $nameAttr = $this->_getCategoryNameAttribute();
        $joinNameCond = $adapter->quoteInto(
            'cat.category_id = name.entity_id AND name.attribute_id = ? AND name.store_id IN(0, cat.store_id)',
            $nameAttr->getAttributeId()
        );

        $select = $adapter->select()
            ->from(array('cat'  => $this->getTable('catalog/category_product_index')), $columns)
            ->join(array('name' => $nameAttr->getBackendTable()), $joinNameCond, array())
            ->where('cat.product_id IN (?)', $productIds)
            ->where('cat.store_id = ?', $storeId)
            ->group('cat.product_id');

        $helper = Mage::getResourceHelper('core');
        $helper->addGroupConcatColumn($select, 'parents', 'cat.category_id', ' ', ',', 'is_parent = 1');
        $helper->addGroupConcatColumn($select, 'anchors', 'cat.category_id', ' ', ',', 'is_parent = 0');
        $helper->addGroupConcatColumn($select, 'positions', array('cat.category_id', 'cat.position'), ' ', '_', 'is_parent = 1');
        $helper->addGroupConcatColumn(
            $select,
            'category_name',
            new Zend_Db_Expr('IF(cat.category_id = 2, "", name.value)'),
            '|'
        );

        $select  = $helper->getQueryUsingAnalyticFunction($select);

        $result = array();
        foreach ($adapter->fetchAll($select) as $row) {
            $data = array(
                'categories'          => array_values(array_filter(explode(' ', $row['parents']))),
                'show_in_categories'  => array_values(array_filter(explode(' ', $row['anchors']))),
                'category_name'       => array_values(array_filter(explode('|', $row['category_name']))),
            );
            foreach (explode(' ', trim($row['positions'])) as $value) {
                $value = explode('_', $value);
                if (count($value) == 2) {
                    list($categoryId, $position) = $value;
                    if ($categoryId && $position) {
                        $data['category_position'][] = array(
                            'category_id' => $categoryId,
                            'position'    => $position
                        );
                    }
                }
            }
            if ($visibility) {
                $data['visibility'] = $row['visibility'];
            }

            $result[$row['product_id']] = $data;
        }

        return $result;
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

        $result = array();
        foreach ($adapter->fetchAll($select) as $row) {
            if (!isset($result[$row['entity_id']])) {
                $result[$row['entity_id']] = array();
            }
            $priceKey = sprintf('price_%s_%s', $row['customer_group_id'], $row['website_id']);
            $result[$row['entity_id']][$priceKey] = round($row['min_price'], 2);

            $discountKey = sprintf('has_discount_%s_%s', $row['customer_group_id'], $row['website_id']);
            $result[$row['entity_id']][$discountKey] = $row['has_discount'];
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
}
