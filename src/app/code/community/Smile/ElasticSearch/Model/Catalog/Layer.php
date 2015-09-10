<?php
/**
 * Custom layer implementation handling navigation through ElasticSearch.
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
class Smile_ElasticSearch_Model_Catalog_Layer extends Mage_Catalog_Model_Layer
{
    /**
     * Returns product collection for current category.
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function getProductCollection()
    {
        /** @var $category Mage_Catalog_Model_Category */
        $category = $this->getCurrentCategory();
        /** @var $collection Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection */
        if (isset($this->_productCollections[$category->getId()])) {
            $collection = $this->_productCollections[$category->getId()];
        } else {
            $collection = Mage::helper('catalogsearch')
                ->getEngine()
                ->getResultCollection()
                ->setStoreId($category->getStoreId());

            $this->prepareProductCollection($collection);
            $this->_productCollections[$category->getId()] = $collection;
        }

        return $collection;
    }

    /**
     * Initialize product collection
     *
     * @param Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection $collection Product collection.
     *
     * @return Mage_Catalog_Model_Layer
     */
    public function prepareProductCollection($collection)
    {
        $query = $collection->getSearchEngineQuery();

        $allowedVisibilities = Mage::getSingleton('catalog/product_visibility')->getVisibleInCatalogIds();
        $query->addFilter('terms', array('visibility' => $allowedVisibilities));

        $allowedStatuses = Mage::getSingleton('catalog/product_status')->getVisibleStatusIds();
        $query->addFilter('terms', array('status' => $allowedStatuses));

        if (Mage::helper('cataloginventory')->isShowOutOfStock() == false) {
            $query->addFilter('terms', array('in_stock' => 1));
        }

        $query->setQueryType('category_products_layer');

        return parent::prepareProductCollection($collection);
    }

    /**
     * Get collection of all filterable attributes for layer products set
     *
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Attribute_Collection
     */
    public function getFilterableAttributes()
    {
        $setIds = $this->_getSetIds();
        if (!$setIds) {
            return array();
        }
        /** @var $collection Mage_Catalog_Model_Resource_Product_Attribute_Collection */
        $collection = Mage::getResourceModel('catalog/product_attribute_collection');
        $collection->setItemObjectClass('catalog/resource_eav_attribute')
            ->setAttributeSetFilter($setIds)
            ->addStoreLabel(Mage::app()->getStore()->getId())
            ->addSetInfo(true)
            ->setOrder('position', 'ASC');

        $collection = $this->_prepareAttributeCollection($collection);
        $collection->load();

        $setIdsWithProductCount = $this->getProductCollection()->getProductCountBySetId();
        $totalSize = array_sum($setIdsWithProductCount);

        foreach ($collection as $attribute) {
            $currentAttributeSets = $attribute->getAttributeSetInfo();
            $currentCountByAttributeSet = array_intersect_key($setIdsWithProductCount, $currentAttributeSets);
            $countProduct = array_sum($currentCountByAttributeSet);

            if ($countProduct < ($attribute->getFacetMinCoverageRate() / 100) * $totalSize) {
                $collection->removeItemByKey($attribute->getAttributeId());
            }
        }

        return $collection;
    }
}
