<?php
/**
 * Common methods used by ES filter blocks.
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
class Smile_ElasticSearch_Block_Catalog_Layer_Filter_Attribute_Suggest extends Mage_Catalog_Block_Layer_Filter_Abstract
{
    /**
     * Retrieve suggest params from the URL.
     *
     * @return array
     */
    public function getSuggestConfig()
    {
        return $this->getRequest()->getParam('suggest', array());
    }

    /**
     * Return the attribute currently use in autocomplete.
     *
     * @return boolean|string
     */
    public function getSuggestAttributeName()
    {
        $attributeName = false;
        $suggestConfig = $this->getSuggestConfig();
        if (isset($suggestConfig['field'])) {
            $attributeName = $suggestConfig['field'];
        }
        return $attributeName;
    }

    /**
     * Get the suggested attribute filter block.
     *
     * @return Smile_ElasticSearch_Block_Catalog_Layer_Filter_Attribute|boolean
     */
    public function getFilterBlock()
    {
        $filterBlockName = $this->getSuggestAttributeName() . '_filter';
        return $this->getLayout()->getBlock($filterBlockName);
    }

    /**
     * Create the layer block into the layout with the right type depending if you are using search or catalog.
     *
     * @return Mage_Catalog_Block_Layer_View
     */
    protected function _getLayerBlock()
    {
        $layerBlockName = 'smile_elasticsearch/catalog_layer_view';
        if ($this->getRequest()->getParam('q', false)) {
            $layerBlockName = 'smile_elasticsearch/catalogsearch_layer';
        }
        return $this->getLayout()->createBlock($layerBlockName, 'layer');
    }

    /**
     * Block init
     *
     * @return Smile_ElasticSearch_Block_Catalog_Layer_Filter_Attribute_Suggest
     */
    protected function _prepareLayout()
    {
        $this->_layerBlock = $this->_getLayerBlock();
        return parent::_prepareLayout();
    }

    /**
     * Render the autocomplete (the curretly suggested filter block).
     *
     * @return string
     */
    protected function _toHtml()
    {
        $html = '';
        $filterBlock = $this->getFilterBlock();
        if ($filterBlock !== false) {
            $html .= $filterBlock->toHtml();
        }
        return $html;
    }
}
