<?php
/**
 * Layer block implementation
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
class Smile_ElasticSearch_Block_Catalog_Layer_View extends Mage_Catalog_Block_Layer_View
{

    /**
     * Templates of the filters.
     * If no template found using the default one (catalog/layer/filter.phtml)
     *
     * See the smile/elaticssearch.xml layout file for a complete example
     *
     * @var array
     */
    protected $_filterTemplates = array();

    /**
     * Boolean block name.
     *
     * @var string
     */
    protected $_booleanFilterBlockName;

    /**
     * Rating block name.
     *
     * @var string
     */
    protected $_ratingFilterBlockName;

    /**
     * Indicates URL rewrites should be used for categories.
     *
     * @var boolean
     */
    protected $_usesUrlRewrite = true;

    /**
     * Modifies default block names to specific ones if engine is active.
     *
     * @return void
     */
    protected function _initBlocks()
    {
        parent::_initBlocks();

        if (Mage::helper('smile_elasticsearch')->isActiveEngine()) {
            $this->_categoryBlockName        = 'smile_elasticsearch/catalog_layer_filter_category';
            $this->_attributeFilterBlockName = 'smile_elasticsearch/catalog_layer_filter_attribute';
            $this->_priceFilterBlockName     = 'smile_elasticsearch/catalog_layer_filter_price';
            $this->_ratingFilterBlockName    = 'smile_elasticsearch/catalog_layer_filter_rating';
            $this->_decimalFilterBlockName   = 'smile_elasticsearch/catalog_layer_filter_decimal';
            $this->_booleanFilterBlockName   = 'smile_elasticsearch/catalog_layer_filter_boolean';
        }
    }

    /**
     * Filters applied to the layer should be added even if not meeting the coverage rate condition.
     *
     * @return array
     */
    protected function _addAppliedFilters()
    {
        $entityType = Mage_Catalog_Model_Product::ENTITY;
        $filters = array();
        foreach ($this->getRequest()->getParams() as $paramName => $value) {
            $attribute = Mage::getSingleton('eav/config')
                ->getAttribute($entityType, $paramName);
            if ($attribute && $attribute->getId()) {
                $filters[$attribute->getAttributeCode() . '_filter'] = $this->_addFilter($attribute);
            }
        }

        return $filters;
    }

    /**
     * Create the block filter for an attribute into the layer.
     *
     * @param Mage_Catalog_Model_Entity_Attribute $attribute Filtered attributes.
     *
     * @return Mage_Catalog_Block_Layer_Filter_Abstract
     */
    protected function _addFilter($attribute)
    {
        $decimalValidationClasses = array('validate-number', 'validate-digits');

        if ($attribute->getAttributeCode() == 'price') {
            $filterBlockName = $this->_priceFilterBlockName;
        } elseif ($attribute->getAttributeCode() == 'rating_filter') {
            $filterBlockName = $this->_ratingFilterBlockName;
        } elseif ($attribute->getSourceModel() == 'eav/entity_attribute_source_boolean') {
            $filterBlockName = $this->_booleanFilterBlockName;
        } elseif ($attribute->getBackendType() == 'decimal' || in_array($attribute->getFrontendClass(), $decimalValidationClasses)) {
            $filterBlockName = $this->_decimalFilterBlockName;
        } else {
            $filterBlockName = $this->_attributeFilterBlockName;
        }

        $filter = $this->getLayout()->createBlock($filterBlockName, $attribute->getAttributeCode() . '_filter')
            ->setLayer($this->getLayer())
            ->setAttributeModel($attribute)
            ->init();

        return $filter;
    }

    /**
     * Get all fiterable attributes of current category
     *
     * @return array
     */
    protected function _getFilterableAttributes()
    {
        $attributes = $this->getData('_filterable_attributes');
        if (is_null($attributes)) {
            $suggestConfig = $this->getRequest()->getParam('suggest');
            if ($this->getRequest()->isAjax() && $suggestConfig && isset($suggestConfig['field'])) {
                $entityType = Mage_Catalog_Model_Product::ENTITY;
                $attribute = Mage::getSingleton('eav/config')->getAttribute($entityType, $suggestConfig['field']);
                $attributes = array($attribute);
            } else {
                $attributes = $this->getLayer()->getFilterableAttributes();
            }
            $this->setData('_filterable_attributes', $attributes);
        }
        return $attributes;
    }

    /**
     * Prepares layout if engine is active.
     * Difference between parent method is addFacetCondition() call on each created block.
     *
     * @return Smile_ElasticSearch_Block_Catalog_Layer_View
     */
    protected function _prepareLayout()
    {
        /** @var $helper Smile_ElasticSearch_Helper_Data */
        $helper = Mage::helper('smile_elasticsearch');
        if (!$helper->isActiveEngine()) {
            parent::_prepareLayout();
        } else {
            $stateBlock = $this->getLayout()->createBlock($this->_stateBlockName)
                ->setLayer($this->getLayer());

            $categoryBlock = $this->getLayout()->createBlock($this->_categoryBlockName, 'category_filter')
                ->setUseUrlRewrites($this->_usesUrlRewrite)
                ->setLayer($this->getLayer())
                ->init();

            $this->setChild('layer_state', $stateBlock);
            $this->setChild('category_filter', $categoryBlock->addFacetCondition());

            $filters = $this->_addAppliedFilters();

            $filterableAttributes = $this->_getFilterableAttributes();

            foreach ($filterableAttributes as $attribute) {
                $blockName = $attribute->getAttributeCode() . '_filter';
                if (!array_key_exists($blockName, $filters)) {
                    $filters[$blockName] = $this->_addFilter($attribute);
                }
                $this->setChild($blockName, $filters[$blockName]->addFacetCondition());
            }

            $this->getLayer()->apply();
        }

        return $this;
    }

    /**
     * Returns current catalog layer.
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer|Mage_Catalog_Model_Layer
     */
    public function getLayer()
    {
        /** @var $helper Smile_ElasticSearch_Helper_Data */
        $helper = Mage::helper('smile_elasticsearch');
        if ($helper->isActiveEngine()) {
            return Mage::getSingleton('smile_elasticsearch/catalog_layer');
        }

        return parent::getLayer();
    }

    /**
     * Check availability display layer options
     *
     * @return bool
     */
    public function canShowOptions()
    {
        foreach ($this->getFilters() as $filter) {
            if ($filter->getItemsCount() > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indicates if the block should be shown or not.
     * Append forced category loading to make the system more resistant to layout changes
     *
     * @return bool
     */
    public function canShowBlock()
    {
        if (!$this->getLayer()->getProductCollection()->isLoaded()) {
            $this->getLayer()->getProductCollection()->getSize();
        }
        return parent::canShowBlock();
    }

    /**
     * Assign a custom template for a given filter
     *
     * @param string $filterName Name of the filter
     * @param string $template   Template
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer Self reference
     */
    public function addFilterTemplate($filterName, $template)
    {
        $this->_filterTemplates[$filterName] = $template;
        return $this;
    }

    /**
     * Custom template handling for children blocks (filters) before to display theme
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer Self reference
     */
    protected function _beforeToHtml()
    {
        if (Mage::helper('smile_elasticsearch')->isActiveEngine()) {
            foreach ($this->_filterTemplates as $filterName => $template) {
                $block = $this->getChild($filterName . '_filter');
                if ($block) {
                    $block->setTemplate($template);
                }
            }

            $this->getLayer()->getProductCollection()->getSize();
        }
        return parent::_beforeToHtml();
    }
}
