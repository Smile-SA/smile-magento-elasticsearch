<?php
/**
 * Attribute filter block
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
class Smile_ElasticSearch_Block_Catalog_Layer_Filter_Attribute extends Smile_ElasticSearch_Block_Catalog_Layer_Filter_Abstract
{
    /**
     * Defines specific filter model name.
     *
     * @see Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
     */
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'smile_elasticsearch/catalog_layer_filter_attribute';
        $this->setIsMultipleSelect(true);
        $this->setTemplate('smile/elasticsearch/catalog/layer/filter.phtml');
    }

    /**
     * Prepares filter model.
     *
     * @return Smile_ElasticSearch_Block_Catalog_Layer_Filter_Attribute
     */
    protected function _prepareFilter()
    {
        $this->_filter->setAttributeModel($this->getAttributeModel());
        $this->_filter->setIsMultipleSelect(true);

        if ($this->isSuggestResponse()) {
            $this->_filter->setSuggestConfig($this->getRequest()->getParam('suggest'));
        }

        return $this;
    }

    /**
     * Adds facet condition to filter.
     *
     * @see Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute::addFacetCondition()
     * @return Smile_ElasticSearch_Block_Catalog_Layer_Filter_Attribute
     */
    public function addFacetCondition()
    {
        $this->_filter->addFacetCondition();

        return $this;
    }

    /**
     * Render the text used into facets suggestion field.
     *
     * @return string
     */
    public function getPlaceholderSearchText()
    {
        $text = $this->__('Search into values');
        $examples = array();
        $items = array_slice($this->getItems(), 0, 2);
        foreach ($items as $item) {
            $examples[] = $item->getLabel();
        }

        if (!empty($examples)) {
            $examples[] = '...';
            $text = $this->__('Search into values (e.g. %s)', implode(', ', $examples));
        }

        return $text;
    }

    /**
     * Retrieve the URL used to fetch suggestions for the facet.
     *
     * @return string
     */
    public function getFacetSuggestUrl()
    {
        $params = array(
            'cat'    => $this->_filter->getLayer()->getCurrentCategory()->getId(),
            'ajax'   => true,
            '_current' => true,
            '_query' => array('p' => null, 'suggest' => null)
        );
        return Mage::getUrl('catalogsearch/ajax/facetSuggest', $params);
    }

    /**
     * Read the suggest config from the URL.
     *
     * @return array|false
     */
    public function getSuggestConfig()
    {
        return $this->getRequest()->getParam('suggest', false);
    }

    /**
     * Indicates if the current rendering is a suggestion one.
     *
     * @return boolean
     */
    public function isSuggestResponse()
    {
        return $this->getSuggestConfig() !== false && $this->getRequest()->isAjax();
    }

    /**
     * Get the suggest text query.
     *
     * @return string
     */
    public function getSuggestQueryText()
    {
        $queryText = '';
        $suggestConfig = $this->getSuggestConfig();
        if ($suggestConfig && isset($suggestConfig['q'])) {
            $queryText = $suggestConfig['q'];
        }
        return $queryText;
    }

    /**
     * Indicates if the facet internal search engine should be enabled or not.
     *
     * @return boolean
     */
    public function isSearchEnabled()
    {
        return $this->hasOthers() || count($this->getItems()) > $this->_filter->getAttributeModel()->getFacetsMaxSize();
    }
}
