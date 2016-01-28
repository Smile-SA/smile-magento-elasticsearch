<?php
/**
 * Register popularity into the search index
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_SearchOptimizer
 * @author    Romain RUAUD <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_SearchOptimizer_Model_Indexer_Popularity extends Mage_Index_Model_Indexer_Abstract
{
    /**
     * Dummy table name, needed to process correct Mview reindex
     */
    const DUMMY_TABLE_NAME   = "popularity";

    /**
     * Metadata view name, used to identify data related to this index
     */
    const METADATA_VIEW_NAME = "popularity";

    /**
     * Metadata group code, used to identify data related to this index
     */
    const METADATA_GROUP_CODE = "popularity";

    /**
     * Retrieve Indexer name
     *
     * @return string
     */
    public function getName()
    {
        return Mage::helper('smile_searchoptimizer')->__('Popularity Indexer');
    }

    /**
     * Retrieve Indexer description
     *
     * @return string
     */
    public function getDescription()
    {
        return Mage::helper('smile_searchoptimizer')->__('Computes popularity data for products.');
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
     * Process event based on event state data
     *
     * @param Mage_Index_Model_Event $event Indexing event.
     *
     * @return void
     */
    protected function _processEvent(Mage_Index_Model_Event $event)
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
        /** Reindex all data from popularity index */
        $dataProvider = $this->_getDataProvider();
        $dataProvider->updateAllData();
    }

    /**
     * Reindex products that have changed in the external popularity index.
     *
     * @param Zend_Date $lastVersionId The last known version index
     *
     * @return void
     */
    public function reindexPartial($lastVersionId)
    {
        $dataProvider = $this->_getDataProvider();
        $dataProvider->updateChangedData($lastVersionId);
    }

    /**
     * Retrieve the data provider associated to popularity data
     *
     * @return Smile_SearchOptimizer_Model_Resource_Engine_Elasticsearch_Mapping_DataProvider_Popularity
     */
    protected function _getDataProvider()
    {
        $engine       = Mage::helper('catalogsearch')->getEngine();
        $mapping      = $engine->getCurrentIndex()->getMapping('product');
        $dataProvider = $mapping->getDataProvider('popularity');

        return $dataProvider;
    }

    /**
     * Retrieve current external index name
     *
     * @return int
     */
    public function getVersionId()
    {
        return $this->_getDataProvider()->getIndexDateTime();
    }
}