<?php
/**
 * Handles rating attribute filtering in layered navigation.
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
class Smile_ElasticSearch_Model_Catalog_Layer_Filter_Rating extends Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
{
    /**
     * @var int
     */
    const RATING_AGG_INTERVAL = 20;

    /**
     * Adds facet condition to product collection.
     *
     * @see Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection::addFacetCondition()
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
     */
    public function addFacetCondition()
    {
        $this->getLayer()
            ->getProductCollection()
            ->addFacetCondition($this->_getFilterField(), array('interval' => self::RATING_AGG_INTERVAL));

        return $this;
    }

    /**
     * Retrieves current items data.
     *
     * @return array
     */
    protected function _getItemsData()
    {
        /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        $attribute = $this->getAttributeModel();
        $this->_requestVar = $attribute->getAttributeCode();

        $layer = $this->getLayer();
        $key = $layer->getStateKey() . '_' . $this->_requestVar;
        $data = $layer->getAggregator()->getCacheData($key);

        if ($data === null) {
            $facets = array_reverse($this->_getFacets(), true);
            $data = array();

            foreach ($facets as $key => $count) {
                $data[] = array(
                    'label' => $key,
                    'value' => $key,
                    'count' => (int) $count,
                );
            }

            $tags = array(
                Mage_Eav_Model_Entity_Attribute::CACHE_TAG . ':' . $attribute->getId()
            );

            $tags = $layer->getStateTags($tags);
            $layer->getAggregator()->saveCacheData($data, $key, $tags);
        }

        return $data;
    }


    /**
     * Retrieves request parameter and applies it to product collection.
     *
     * @param Zend_Controller_Request_Abstract $request     Request containing filter var and value
     * @param Mage_Core_Block_Abstract         $filterBlock Layer block representing the filter
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
     */
    public function apply(Zend_Controller_Request_Abstract $request, $filterBlock)
    {
        $filter = $request->getParam($this->_requestVar);
        if (is_array($filter) || null === $filter) {
            return $this;
        }

        $text = Mage::helper('smile_elasticsearch')->__('Rating : %d / 5', $filter / self::RATING_AGG_INTERVAL);
        if ($this->_isValidFilter($filter) && strlen($text)) {
            $this->applyFilterToCollection($this, (int) $filter);
            $this->getLayer()->getState()->addFilter($this->_createItem($text, $filter));
            $this->_items = array();
        }

        return $this;
    }

    /**
     * Applies filter to product collection.
     *
     * @param Mage_Catalog_Model_Layer_Filter_Attribute $filter Filter to be applied
     * @param mixed                                     $value  Value of the filter
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
     */
    public function applyFilterToCollection($filter, $value)
    {
        $value = array(
            $this->getRequestVar() => array(
                'from' => $value,
                'to' => $value + self::RATING_AGG_INTERVAL,
                'include_lower' => true
            )
        );

        $this->getLayer()
            ->getProductCollection()
            ->addFqRangeFilter($value);

        return $this;
    }

}
