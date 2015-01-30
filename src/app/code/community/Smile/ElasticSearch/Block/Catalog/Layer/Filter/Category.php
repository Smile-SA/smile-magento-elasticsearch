<?php
/**
 * Categories filter block
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
class Smile_ElasticSearch_Block_Catalog_Layer_Filter_Category extends Smile_ElasticSearch_Block_Catalog_Layer_Filter_Abstract
{
    /**
     * Defines specific filter model name.
     *
     * @see Smile_ElasticSearch_Model_Catalog_Layer_Filter_Category
     */
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'smile_elasticsearch/catalog_layer_filter_category';
    }

    /**
     * Init filter model object
     *
     * @return Mage_Catalog_Block_Layer_Filter_Abstract
     */
    protected function _initFilter()
    {
        if (!$this->_filterModelName) {
            Mage::throwException(Mage::helper('catalog')->__('Filter model name must be declared.'));
        }
        $this->_filter = Mage::getModel($this->_filterModelName)
            ->setLayer($this->getLayer());

        if ($this->getUseUrlRewrites()) {
            $this->_filter->setUseUrlRewrites(true);
        }

        $this->_prepareFilter();

        $this->_filter->apply($this->getRequest(), $this);
        return $this;
    }

    /**
     * Adds facet condition to filter.
     *
     * @see Smile_ElasticSearch_Model_Catalog_Layer_Filter_Category::addFacetCondition()
     * @return Smile_ElasticSearch_Block_Catalog_Layer_Filter_Attribute
     */
    public function addFacetCondition()
    {
        $this->_filter->addFacetCondition();

        return $this;
    }
}
