<?php
/**
 * Search index refresh indexer.
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
class Smile_ElasticSearch_Model_Index_Action_Fulltext_Refresh
    extends Enterprise_CatalogSearch_Model_Index_Action_Fulltext_Refresh
{
    /**
     * Run full reindex only when needed
     *
     * @return Enterprise_CatalogSearch_Model_Index_Action_Fulltext_Refresh
     *
     * @throws Enterprise_Index_Model_Action_Exception
     */
    public function execute()
    {
        if (Mage::helper('smile_elasticsearch')->isActiveEngine() == false) {
            parent::execute();
        } else {
            $this->_getLastVersionId();
            $this->_metadata->setInProgressStatus()->save();

            $engine = Mage::helper('catalogsearch')->getEngine();
            $index = $engine->getCurrentIndex();

            $index->prepareNewIndex();
            foreach ($index->getAllMappings() as $mapping) {
                $mapping->rebuildIndex();
            }
            $index->installNewIndex();

            $this->_updateMetadata();
            $this->_app->dispatchEvent('after_reindex_process_catalogsearch_index', array());
        }

        return $this;
    }
}
