<?php
/**
 * Override search indexing for elastic search
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
class Smile_ElasticSearch_Model_Indexer_Fulltext extends Mage_CatalogSearch_Model_Indexer_Fulltext
{

    /**
     * Category products reindexing
     *
     * @param Mage_Index_Model_Event $event Event to be indexed
     *
     * @return Mage_CatalogSearch_Model_Indexer_Fulltext Self reference
     */
    protected function _registerCatalogCategoryEvent(Mage_Index_Model_Event $event)
    {
        if (Mage::helper('smile_elasticsearch')->isActiveEngine() && $event->getType() == Mage_Index_Model_Event::TYPE_SAVE) {
            /* @var $category Mage_Catalog_Model_Category */
            $category   = $event->getDataObject();
            $productIds = $category->getAffectedProductIds();

            if ($productIds) {
                $event->addNewData('catalogsearch_product_ids', $productIds);
                $event->addNewData('catalogsearch_force_reindex', true);
            } else {
                $movedCategoryId = $category->getMovedCategoryId();
                if ($movedCategoryId) {
                    $productIds = $category->getProductCollection()->getAllIds();
                    if (!empty($productIds)) {
                        $event->addNewData('catalogsearch_product_ids', $productIds);
                        $event->addNewData('catalogsearch_force_reindex', true);
                    }
                }
            }
        } else {
            parent::_registerCatalogCategoryEvent($event);
        }
        return $this;
    }
}

