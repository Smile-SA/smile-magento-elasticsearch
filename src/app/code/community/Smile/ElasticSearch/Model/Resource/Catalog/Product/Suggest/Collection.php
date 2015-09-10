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
     * Get the ES query model associated with the product collection.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function getSearchEngineQuery()
    {
        if ($this->_searchEngineQuery === null) {

            $this->_searchEngineQuery = $this->_engine->createQuery(
                'product', 'smile_elasticsearch/engine_elasticsearch_query_autocomplete'
            );

            if ($this->getStoreId()) {
                $store = Mage::app()->getStore();
                $this->_searchEngineQuery->setLanguageCode(
                    Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store)
                );
            }
        }

        return $this->_searchEngineQuery;
    }
}
