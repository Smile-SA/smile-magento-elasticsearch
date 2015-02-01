<?php
/**
 * Handles attribute filtering in layered navigation.
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
class Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute extends Mage_Catalog_Model_Layer_Filter_Attribute
{
    /**
     * @var array()
     */
    protected $_appliedItems = array();

    /**
     * @var array()
     */
    protected $_rawFilter = array();

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
        $options = array('field' => $this->_getFilterField());
        $query->addFacet($this->_requestVar, 'terms', $options);

        return $this;
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

        if ($filter === null) {
            return $this;
        }
        if (!is_array($filter)) {
            $filter = array($filter);
        }

        $this->_rawFilter = array_filter($filter);


        if (!empty($this->_rawFilter)) {
            $this->applyFilterToCollection($filter);
            $this->getLayer()->getState()->addFilter($this->_createItem(implode(' , ', $this->_rawFilter), $filter));
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
        if (!is_array($value)) {
            $value = array($value);
        }

        $query = $this->getLayer()->getProductCollection()->getSearchEngineQuery();
        $query->addFilter('terms', array($this->_getFilterField() => $value), $this->_requestVar);

        return $this;
    }

    /**
     * Returns facets data of current attribute.
     *
     * @return array
     */
    protected function _getFacets()
    {
        /** @var $productCollection Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection */
        $productCollection = $this->getLayer()->getProductCollection();
        $fieldName = $this->_getFilterField();
        $facets = $productCollection->getFacetedData($this->_requestVar);
        return $facets;
    }

    /**
     * Returns attribute field name.
     *
     * @return string
     */
    protected function _getFilterField()
    {
        /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        $attribute = $this->getAttributeModel();
        $currentIndex = Mage::helper('catalogsearch')->getEngine()->getCurrentIndex();
        $mapping = $currentIndex->getMapping('product');
        $store = Mage::app()->getStore();
        $languageCode = Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store);
        $fieldName = $mapping->getFieldName($attribute->getAttributeCode(), $languageCode, 'facet');
        return $fieldName;
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

        $facets = $this->_getFacets();
        $data = array();
        if (array_sum($facets) > 0) {

            $options = array();
            foreach ($facets as $label => $count) {
                $options[] = array(
                    'label' => $label,
                    'value' => $label,
                    'count' => $count,
                );
            }

            foreach ($options as $option) {
                if (is_array($option['value']) || !Mage::helper('core/string')->strlen($option['value'])) {
                    continue;
                }
                $count = 0;
                $label = $option['label'];
                if (isset($facets[$option['value']])) {
                    $count = (int) $facets[$option['value']];
                }
                if (!$count && $this->_getIsFilterableAttribute($attribute) == self::OPTIONS_ONLY_WITH_RESULTS) {
                    continue;
                }

                $data[$option['value']] = array(
                    'label' => $label,
                    'value' => $option['value'],
                    'count' => (int) $count,
                );
            }

            foreach ($this->_rawFilter as $value) {
                if (!isset($data[$value])) {
                    $data[] = array('label' => $value, 'value' => $value, 'count' => 0);
                }
            }

            $data = array_values($data);
        }

        return $data;
    }


    /**
     * Checks if given filter is valid before being applied to product collection.
     *
     * @param string $filter Validate a Filter to be validated
     *
     * @return bool
     */
    protected function _isValidFilter($filter)
    {
        return !empty($filter);
    }

    /**
     * Create filter item object
     *
     * @param string $label Label of the filter value
     * @param mixed  $value Value of the filter
     * @param int    $count Number of result (default is 0)
     *
     * @return Mage_Catalog_Model_Layer_Filter_Item
     */
    protected function _createItem($label, $value, $count=0)
    {
        $isSelected = false;

        if ($this->getIsMultipleSelect() && $value) {
            if (in_array($value, $this->_rawFilter)) {
                $isSelected = true;
            }

            $values = $this->_rawFilter;

            if (($key = array_search($value, $values)) !== false) {
                unset($values[$key]);
                $value = array_values($values);
            } else {
                if (!is_array($value)) {
                    $value = array($value);
                }
                $value = array_merge($values, $value);
            }
        }

        $item = parent::_createItem($label, $value, $count);
        $item->setSelected($isSelected);


        return $item;
    }
}
