<?php
/**
 * Search terms dedicated observer
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
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Model_Search_Term_Observer
{
    /**
     * Append products positions to the current search term if needed
     *
     * @param Varien_Event_Observer $observer The observer
     *
     * @event catalogsearch_query_save_after
     *
     * @return void Nothing
     */
    public function saveProductsPositions(Varien_Event_Observer $observer)
    {
        $searchTerm = $observer->getCatalogsearchQuery();
        $positions  = $searchTerm->getData("position");

        if (!is_array($positions)) {
            $positions = array();
        }

        $filteredPositions = array_filter($positions, 'is_numeric');
        $resourceModel     = Mage::getResourceModel("smile_elasticsearch/search_term_product_position");
        $previousProducts  = $resourceModel->getProductIdsByQuery($searchTerm);

        $resourceModel->saveProductsPositions($filteredPositions, $searchTerm);

        $productIdsToReindex = array_unique(array_merge($previousProducts, array_keys($filteredPositions)));

        // If Enterprise version, Mview index will handle editing, otherwise, process reindex
        if (!Mage::helper("smile_elasticsearch")->isEnterpriseSupportEnabled()) {

            Mage::getSingleton('index/indexer')->processEntityAction(
                $searchTerm->setProductIds($productIdsToReindex),
                Smile_ElasticSearch_Model_Indexer_Search_Terms_Position::ENTITY,
                Mage_Index_Model_Event::TYPE_SAVE
            );

        } else {

            $helper = Mage::helper('smile_elasticsearch/index');

            if ($helper->isLiveProductPositionInSearchReindexEnabled()) {

                $client = Mage::getModel('enterprise_mview/client');
                $client->init(Mage::helper('enterprise_index')->getIndexerConfigValue('search_term_product_position', 'index_table'));
                $arguments = array('value' => $productIdsToReindex);
                $client->execute('smile_elasticsearch/index_action_search_terms_refresh_row', $arguments);

            }
        }
    }

    /**
     * Append a sort by our custom positions when processing a fulltext search query
     *
     * @param Varien_Event_Observer $observer The observer
     *
     * @event smile_elasticsearch_query_assembled
     *
     * @return Smile_ElasticSearch_Model_Search_Term_Observer self reference
     */
    public function applyProductsPositions(Varien_Event_Observer $observer)
    {
        $data  = $observer->getQueryData();
        $query = $data->getQuery();

        $fullTextQuery = Mage::helper('catalogsearch')->getQuery();

        if ($fullTextQuery->getId()) {

            $optimizer = Mage::getModel("smile_elasticsearch/search_term_optimizer");
            $query     = $optimizer->applyCustomProductsPositions($query, $fullTextQuery);

            $data->setQuery($query);
        }

        return $this;
    }
}