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
        $query = $this->getLayer()->getProductCollection()->getSearchEngineQuery();
        $options = array('interval' => self::RATING_AGG_INTERVAL, 'field' => $this->_getFilterField());
        $query->addFacet($this->_getFilterField(), 'histogram', $options);

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

            $data      = array();
            $items    = array_reverse($this->_getFacet()->getItems(), true);
            $sumCount  = 0;

            $maxValue = current(array_keys($items));

            while (($maxValue = $maxValue - self::RATING_AGG_INTERVAL) && $maxValue >0) {
                if (!isset($items[$maxValue])) {
                    $items[$maxValue] = 0;
                }
            }

            foreach ($items as $key => $count) {
                $sumCount  += $count ;
                $data[] = array(
                    'label' => $key,
                    'value' => $key,
                    'count' => (int) $sumCount,
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

        $text = Mage::helper('smile_elasticsearch')->__('%d / 5 and more', $filter / self::RATING_AGG_INTERVAL);
        if ($this->_isValidFilter($filter) && strlen($text)) {
            $this->applyFilterToCollection((int) $filter);
            $this->getLayer()->getState()->addFilter($this->_createItem($text, $filter));
        }

        return $this;
    }

    /**
     * Applies filter to product collection.
     *
     * @param mixed $value Value of the filter
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
     */
    public function applyFilterToCollection($value)
    {
        $limits = array('gte' => $value);
        $query = $this->getLayer()->getProductCollection()->getSearchEngineQuery();
        $query->addFilter('range', array($this->_getFilterField() => $limits), $this->_getFilterField());

        return $this;
    }
}
