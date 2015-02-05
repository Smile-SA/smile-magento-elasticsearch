<?php
/**
 * Popular search phrases autocomplete block implementation.
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
class Smile_ElasticSearch_Block_Catalogsearch_Autocomplete_Suggest_Terms extends Mage_Core_Block_Template
{
    /**
     * @var null|array
     */
    protected $_suggestData = null;

    /**
     * Retrive the list of terms that would be suggested to the user
     *
     * @return array
     */
    public function getSuggestData()
    {
        if (!$this->_suggestData) {
            $maxSize = $this->getMaxSize();
            $collection = $this->helper('catalogsearch')->getSuggestCollection();
            $collection->setPageSize($maxSize);
            $query = $this->helper('catalogsearch')->getQueryText();
            $counter = 0;
            $data = array();
            foreach ($collection as $item) {
                $_data = array(
                    'title' => $item->getQueryText(),
                    'row_class' => (++$counter)%2?'odd':'even',
                    'num_of_results' => $item->getNumResults()
                );

                if ($item->getQueryText() == $query) {
                    array_unshift($data, $_data);
                } else {
                    $data[] = $_data;
                }
            }
            $this->_suggestData = $data;
        }
        return $this->_suggestData;
    }

    /**
     * Get number of suggestion to display
     *
     * @return int
     */
    public function getMaxSize()
    {
        return Mage::getStoreConfig('elasticsearch_advanced_search_settings/popular_terms_autocomplete/max_size');
    }

}
