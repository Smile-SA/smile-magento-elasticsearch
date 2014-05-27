<?php
/**
 * Category autocomplete collection
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
class Smile_ElasticSearch_Model_Resource_Catalog_Category_Suggest_Collection
   extends Mage_Catalog_Model_Resource_Category_Collection
{
    /**
     * $_suggestQuery
     */
    protected $_suggestQuery = null;

    /**
     * $_suggestionsIds
     */
    protected $_suggestionsIds = null;

    /**
     * $_suggestionsOutput
     */
    protected $_suggestionsOutput = array();

    /**
     * $_engine
     */
    protected $_engine;

    /**
     * $_isSuggestionFilterSet
     */
    protected $_isSuggestionFilterSet = false;

    /**
     * Register suggest input text.
     *
     * @param string $query The text input
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Suggest_Collection
     */
    public function addSuggestFilter($query)
    {
        $this->_suggestQuery = $query;
        return $this;
    }

    /**
     * Defines current search engine.
     *
     * @param Smile_ElasticSearch_Model_Resource_Engine_Abstract $engine Search engine to be set
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function setEngine(Smile_ElasticSearch_Model_Resource_Engine_Abstract $engine)
    {
        $this->_engine = $engine;

        return $this;
    }

    /**
     * Get size of the csuggest collection
     *
     * @return int Size of the collection
     */
    public function getSize()
    {
        if ($this->_isSuggestionFilterSet === false) {
            $this->addIdFilter($this->getSuggestionIds());
            $this->_isSuggestionFilterSet = true;
        }
        return parent::getSize();
    }

    /**
     * Apply filters before load
     *
     * @return Mage_Catalog_Model_Resource_Product_Collection Self reference
     */
    protected function _beforeLoad()
    {
        if ($this->_isSuggestionFilterSet === false) {
            $this->addIdFilter($this->getSuggestionIds());
            $this->_isSuggestionFilterSet = true;
        }
        return parent::_beforeLoad();
    }

    /**
     * Return ids of the suggested product
     *
     * @return array Ids of the selected products
     */
    public function getSuggestionIds()
    {
        if (is_null($this->_suggestionsIds) && !is_null($this->_suggestQuery)) {
            $suggestions = $this->_engine->suggestProduct($this->_suggestQuery);
            $idsFilter = array();
            Mage::log($suggestions);
            foreach ($suggestions as $suggestion) {
                if (isset($suggestion['payload']) && isset($suggestion['payload']['category_id'])) {
                    $categoryId = $suggestion['payload']['category_id'];
                    $idsFilter[] = $categoryId;
                    $this->_suggestionsOutput[$categoryId] = $suggestion['text'];
                }
            }

            if (empty($idsFilter)) {
                $idsFilter = array(0);
            }

            $this->_suggestionsIds = $idsFilter;
        }

        return $this->_suggestionsIds;
    }

    /**
     * Load suggestion text into items
     *
     * @return Mage_Catalog_Model_Resource_Product_Collection Self reference
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();

        foreach ($this->_items as $item) {

            $item->setOutputText($this->_suggestionsOutput[$item->getId()]);
        }

        return $this;
    }
}