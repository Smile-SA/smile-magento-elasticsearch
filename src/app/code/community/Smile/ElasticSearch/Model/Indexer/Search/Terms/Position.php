<?php
/**
 * Register custom products positions for search terms into the search index
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
 * @author    Romain RUAUD <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Model_Indexer_Search_Terms_Position extends Mage_Index_Model_Indexer_Abstract
{
    /**
     * Metadata view name, used to identify data related to this index
     */
    const METADATA_VIEW_NAME = "search_term_product_position";

    /**
     * Metadata group code, used to identify data related to this index
     */
    const METADATA_GROUP_CODE = "search_term_product_position";

    /**
     * CatalogSearch Query entity code, defined here because not existing on standard model
     */
    const ENTITY              = "search_term_product_position";

    /**
     * Index math: product save, category save, store save
     * store group save, config save
     *
     * @var array
     */
    protected $_matchedEntities = array(
        self::ENTITY => array(
            Mage_Index_Model_Event::TYPE_SAVE
        )
    );

    /**
     * Process event based on event state data
     *
     * @param Mage_Index_Model_Event $event Indexing event.
     *
     * @return void
     */
    protected function _processEvent(Mage_Index_Model_Event $event)
    {
        $searchTerm = $event->getDataObject();
        if ($event->getType() == Mage_Index_Model_Event::TYPE_SAVE) {
            $this->reindex($searchTerm);
        }
    }

    /**
     * Reindex a single search query.
     *
     * @param Mage_CatalogSearch_Model_Query $query The search query.
     *
     * @return void
     */
    public function reindex($query)
    {
        /** Reindex all data from search terms custom positions index */
        $engine       = Mage::helper('catalogsearch')->getEngine();
        $mapping      = $engine->getCurrentIndex()->getMapping('product');
        $dataprovider = $mapping->getDataProvider('search_terms_position');

        $dataprovider->updateAllData($query->getStoreId(), $query->getProductIds());
    }

    /**
     * Retrieve Indexer name
     *
     * @return string
     */
    public function getName()
    {
        return Mage::helper('smile_elasticsearch')->__('Search Terms products positions Indexer');
    }

    /**
     * Retrieve Indexer description
     *
     * @return string
     */
    public function getDescription()
    {
        return Mage::helper('smile_elasticsearch')->__('Computes custom positions for products by search terms.');
    }

    /**
     * Register data required by process in event object
     *
     * @param Mage_Index_Model_Event $event Indexing event.
     *
     * @return void
     */
    protected function _registerEvent(Mage_Index_Model_Event $event)
    {
        return;
    }

    /**
     * Reindex everything.
     *
     * @return void
     */
    public function reindexAll()
    {
        /** Reindex all data from search terms custom positions index */
        $engine       = Mage::helper('catalogsearch')->getEngine();
        $mapping      = $engine->getCurrentIndex()->getMapping('product');
        $dataprovider = $mapping->getDataProvider('search_terms_position');
        $dataprovider->updateAllData();
    }
}