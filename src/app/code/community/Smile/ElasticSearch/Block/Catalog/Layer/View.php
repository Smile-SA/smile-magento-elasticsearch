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

            $categoryBlock = $this->getLayout()->createBlock($this->_categoryBlockName)
                ->setUseUrlRewrites(true)
                ->setLayer($this->getLayer())
                ->init();

            $this->setChild('layer_state', $stateBlock);
            $this->setChild('category_filter', $categoryBlock->addFacetCondition());

            $filterableAttributes = $this->_getFilterableAttributes();
            //var_dump($filterableAttributes); die;
            $filters = array();
            foreach ($filterableAttributes as $attribute) {

                if ($attribute->getAttributeCode() == 'price') {
                    $filterBlockName = $this->_priceFilterBlockName;
                } elseif ($attribute->getAttributeCode() == 'rating_filter') {
                    $filterBlockName = $this->_ratingFilterBlockName;
                } elseif ($attribute->getBackendType() == 'decimal') {
                    $filterBlockName = $this->_decimalFilterBlockName;
                } elseif ($attribute->getSourceModel() == 'eav/entity_attribute_source_boolean') {
                    $filterBlockName = $this->_booleanFilterBlockName;
                } else {
                    $filterBlockName = $this->_attributeFilterBlockName;
                }

                $filters[$attribute->getAttributeCode() . '_filter'] = $this->getLayout()->createBlock($filterBlockName)
                    ->setLayer($this->getLayer())
                    ->setAttributeModel($attribute)
                    ->init();
            }
            //die;
            foreach ($filters as $filterName => $block) {
                $this->setChild($filterName, $block->addFacetCondition());
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
        foreach ($this->_filterTemplates as $filterName => $template) {
            $block = $this->getChild($filterName . '_filter');
            if ($block) {
                $block->setTemplate($template);
            }
        }

        return parent::_beforeToHtml();
    }
}
