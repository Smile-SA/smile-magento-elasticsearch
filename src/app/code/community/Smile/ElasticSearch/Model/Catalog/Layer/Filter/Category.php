<?php
/**
 * Handles category filtering in layered navigation.
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
class Smile_ElasticSearch_Model_Catalog_Layer_Filter_Category extends Mage_Catalog_Model_Layer_Filter_Category
{
    /**
     * Adds category filter to product collection.
     *
     * @param Mage_Catalog_Model_Category $category Category to filter
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Category
     */
    public function addCategoryFilter($category)
    {
        $this->setCategory($category);


        Mage::dispatchEvent('category_filter_add_filter_to_collection_before', array('filter' => $this, 'category' => $category));

        if (!$this->getProductCollectionFilterSet()) {
            $query = $this->getLayer()->getProductCollection()->getSearchEngineQuery();
            $categoryId = $category->getId();
            $qs = "(categories:{$categoryId} OR show_in_categories:{$categoryId})";
            $query->addFilter('query', array('query_string' => $qs));
        }

        return $this;
    }

    /**
     * Adds facet condition to product collection.
     *
     * @see Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection::addFacetCondition()
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Category
     */
    public function addFacetCondition()
    {
        $category = $this->getCategory();

        Mage::dispatchEvent('category_filter_add_facet_to_collection_before', array('filter' => $this, 'category' => $category));

        if (!$this->getProductCollectionFacetSet()) {
            $query = $this->getLayer()->getProductCollection()->getSearchEngineQuery();
            $options = array('script_field' => 'doc.categories.values + doc.show_in_categories.values');
            $query->addFacet('categories', 'terms', $options);
        }

        return $this;
    }

    /**
     * Retrieves request parameter and applies it to product collection.
     *
     * @param Zend_Controller_Request_Abstract $request     Request containing filter var and value
     * @param Mage_Core_Block_Abstract         $filterBlock Layer block representing the filter
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Category
     */
    public function apply(Zend_Controller_Request_Abstract $request, $filterBlock)
    {
        $minLevel = Mage::getStoreConfig('catalog/search/elasticsearch_min_category_filter_level');

        if (!$this->getUseUrlRewrites()) {
            $minLevel = 2;
        }

        $filter = (int) $request->getParam($this->getRequestVar());
        if ($filter) {
            $this->_categoryId = $filter;
        }

        /** @var $category Mage_Catalog_Model_Category */
        $category = $this->getCategory();
        if (!Mage::registry('current_category_filter')) {
            Mage::register('current_category_filter', $category);
        }

        if (!$filter && $this->getCategory()) {
            if ($this->getCategory()->getLevel() >= $minLevel) {
                $filter = $this->getCategory()->getId();
            } else if ($this->getCategory()) {
                $this->addCategoryFilter($category);
            }
        }

        if ($filter) {
            $this->addCategoryFilter($category);
        }

        $this->_appliedCategory = Mage::getModel('catalog/category')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($filter);

        if ($this->_isValidCategory($this->_appliedCategory)) {
            $this->getLayer()->getState()->addFilter(
                $this->_createItem($this->_appliedCategory->getName(), $this->_appliedCategory->getId())
            );
        }

        return $this;
    }

    /**
     * Additional validation when on a category page, so the sub category of the store root category is not added as filter.
     *
     * @param Mage_Catalog_Model_Category $category Category to be validated.
     *
     * @return bool
     */
    protected function _isValidCategory($category)
    {
        $rootId = (int) Mage::app()->getStore()->getRootCategoryId();
        if ($this->getUseUrlRewrites() && $category->getParentId() === $rootId) {
            return false;
        }

        return parent::_isValidCategory($category);
    }

    /**
     * Retrieves current items data.
     *
     * @return array
     */
    protected function _getItemsData()
    {
        $layer = $this->getLayer();
        $key = $layer->getStateKey().'_SUBCATEGORIES';
        $data = $layer->getCacheData($key);

        if ($data === null) {
            $categories = $this->getCategory()->getChildrenCategories();

            /** @var $productCollection Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection */
            $productCollection = $layer->getProductCollection();
            $facets = $productCollection->getFacetedData('categories');

            $data = array();
            foreach ($categories as $category) {
                /** @var $category Mage_Catalog_Model_Category */
                $categoryId = $category->getId();
                if (isset($facets[$categoryId])) {
                    $category->setProductCount($facets[$categoryId]);
                } else {
                    $category->setProductCount(0);
                }
                if ($category->getIsActive() && $category->getProductCount()) {
                    $data[$categoryId] = array(
                        'label' => Mage::helper('core')->escapeHtml($category->getName()),
                        'value' => $categoryId,
                        'count' => $category->getProductCount(),
                    );
                }
            }

            if ($this->getUseUrlRewrites()) {
                $data = $this->_appendUrlRewritesToData($data);
            }

            $data = array_values($data);

            $tags = $layer->getStateTags();
            $layer->getAggregator()->saveCacheData($data, $key, $tags);
        }

        return $data;
    }


    /**
     * Create filter item object
     *
     * @param string $label Label of the filter value
     * @param mixed  $value Value of the filter
     * @param int    $count Number of result (default is 0)
     * @param string $url   URL of the category
     *
     * @return Mage_Catalog_Model_Layer_Filter_Item
     */
    protected function _createItem($label, $value, $count=0, $url = null)
    {
        $item = parent::_createItem($label, $value, $count);

        if ($this->getUseUrlRewrites()) {
            $item = Mage::getModel('smile_elasticsearch/catalog_layer_filter_item_category')
                ->setFilter($this)
                ->setLabel($label)
                ->setValue($value)
                ->setCount($count)
                ->setCategoryUrl($url);
        }

        return $item;
    }

    /**
     * Append categories URL rewrites to facet items.
     *
     * @param array $data Facet items data
     *
     * @return array
     */
    protected function _appendUrlRewritesToData($data)
    {

        $categoryIds = array_keys($data);
        $categories = Mage::getResourceModel('catalog/category_collection')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->addIdFilter($categoryIds)
            ->addUrlRewriteToResult();

        foreach ($categories as $currentCategory) {
            $data[$currentCategory->getId()]['category_url'] = $currentCategory->getUrl();
        }

        return $data;
    }

    /**
     * Initialize filter items
     *
     * @return  Mage_Catalog_Model_Layer_Filter_Abstract
     */
    protected function _initItems()
    {
        $data = $this->_getItemsData();
        $items=array();
        foreach ($data as $itemData) {
            $items[] = $this->_createItem(
                $itemData['label'],
                $itemData['value'],
                $itemData['count'],
                isset($itemData['category_url']) ? $itemData['category_url'] : null
            );
        }
        $this->_items = $items;
        return $this;
    }
}
