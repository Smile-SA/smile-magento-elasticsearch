<?php
/**
 * Collection enabling virtual categories attribute parsing
 * and correct product count.
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
class Smile_VirtualCategories_Model_Resource_Catalog_VirtualCategory_Collection extends Mage_Catalog_Model_Resource_Category_Collection
{
    /**
     * Init virtual category attributes using the backend model when category load.
     *
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();

        if (array_key_exists('virtual_category', $this->_selectAttributes)) {
            $virtualCategoryBackendModel = Mage::getModel('smile_virtualcategories/category_attributes_backend_virtual');
            foreach ($this->_items as $item) {
                $virtualCategoryBackendModel->afterLoad($item);
            }
        }

        return $this;
    }

    /**
     * Load product count for specified items
     *
     * @param array   $items        Items the product count should be loaded for.
     * @param boolean $countRegular Get product count for regular (non-anchor) categories.
     * @param boolean $countAnchor  Get product count for anchor categories.
     *
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    public function loadProductCount($items, $countRegular = true, $countAnchor = true)
    {
        $query = $this->getQuery();

        if ($query !== false) {

            $query->setPageParams(0, 0);
            $queries = array();

            foreach ($items as $item) {
                $queries[$item->getId()] = $item->getVirtualRule()->getSearchQuery();
            }

            $options = array('queries' => $queries, 'prefix' => 'categories_');
            $query->addFacet('categories', 'queryGroup', $options);

            $response = $query->search();

            foreach ($items as $item) {
                $item->setProductCount($response['faceted_data']['categories'][$item->getId()]);
            }
        }

        return $this;
    }

    /**
     * Init a new ES query used to run a facet queries query to count product per category.
     *
     * @return false|Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function getQuery()
    {
        $query = false;
        $engine = Mage::helper('catalogsearch')->getEngine();

        if ($engine instanceof Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch) {
            $query = $engine->createQuery('product');
            $query->addFilter('terms', array('store_id' => $this->getProductStoreId()));
        }

        return $query;
    }
}
