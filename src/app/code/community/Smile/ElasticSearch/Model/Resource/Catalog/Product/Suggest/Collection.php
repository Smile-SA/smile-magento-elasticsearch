<?php
/**
 * Custom catalog product collection model product suggest through ElasticSearch.
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
class Smile_ElasticSearch_Model_Resource_Catalog_Product_Suggest_Collection 
    extends Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
{
    /**
     * @var null|string
     */
    protected $_suggestQuery   = null;
    
    /**
     * @var null|array
     */
    protected $_suggestionsIds = null;
    
    /**
     * @var bool
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
     * Apply filters before load
     * 
     * @return Mage_Catalog_Model_Resource_Product_Collection Self reference
     */
    protected function _beforeLoad()
    {
        if ($this->_isSuggestionFilterSet === false) {
            $this->addFqFilter(array('id' => $this->getSuggestionIds()));
            $this->_isSuggestionFilterSet = true;
        }
        return parent::_beforeLoad();
    }
    
    /**
     * Get size of the csuggest collection
     *
     * @return int Size of the collection
     */
    public function getSize()
    {
        if ($this->_isSuggestionFilterSet === false) {
            $this->addFqFilter(array('id' => $this->getSuggestionIds()));
            $this->_isSuggestionFilterSet = true;
        }
        return parent::getSize();   
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
            
            foreach ($suggestions as $suggestion) {
                if (isset($suggestion['payload']) && $suggestion['payload']['product_id']) {
                    $idsFilter[] = $suggestion['payload']['product_id'];
                }
            }
            
            if (empty($idsFilter)) {
                $idsFilter = array(0);
            }
            
            $this->_suggestionsIds = $idsFilter;
        }
        
        return $this->_suggestionsIds;
    }
}
