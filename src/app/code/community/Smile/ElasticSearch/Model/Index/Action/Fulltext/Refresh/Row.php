<?php
/**
 * Search index row indexer.
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
class Smile_ElasticSearch_Model_Index_Action_Fulltext_Refresh_Row
    extends Enterprise_CatalogSearch_Model_Index_Action_Fulltext_Refresh_Row
{

    /**
     * Refresh rows by ids from changelog
     *
     * Reindex modified produts rows
     *
     * @return Enterprise_CatalogSearch_Model_Index_Action_Fulltext_Refresh_Changelog
     *
     * @throws Enterprise_Index_Model_Action_Exception
     */
    public function execute()
    {
        if (Mage::helper('smile_elasticsearch')->isActiveEngine() == false) {
            parent::execute();
        } else {
            $engine = Mage::helper('catalogsearch')->getEngine();

            $this->_setProductIdsFromValue();
            $productIds = $this->_productIds;
            $this->_setProductIdsFromParents();
            $productIds = array_merge($productIds, $this->_productIds);

            $engine->cleanIndex(null, $productIds);
            $engine->getCurrentIndex()
                ->getMapping('product')
                ->rebuildIndex(null, $productIds);
        }

        return $this;
    }

}