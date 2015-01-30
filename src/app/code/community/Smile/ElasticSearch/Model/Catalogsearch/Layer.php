<?php
/**
 * Custom layer implementation handling search through ElasticSearch.
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
class Smile_ElasticSearch_Model_Catalogsearch_Layer extends Mage_CatalogSearch_Model_Layer
{
    /**
     * Returns product collection for current category.
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function getProductCollection()
    {
        $category = $this->getCurrentCategory();
        if (isset($this->_productCollections[$category->getId()])) {
            $collection = $this->_productCollections[$category->getId()];
        } else {
            /** @var $collection Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection */
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

        $allowedVisibilities = Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds();
        $query->addFilter('terms', array('visibility' => $allowedVisibilities));

        $allowedStatuses = Mage::getSingleton('catalog/product_status')->getVisibleStatusIds();
        $query->addFilter('terms', array('status' => $allowedStatuses));

        if (Mage::helper('cataloginventory')->isShowOutOfStock() == false) {
            $query->addFilter('terms', array('in_stock' => 1));
        }

        $query->setQueryType('product_search_layer');

        return parent::prepareProductCollection($collection);
    }
}
