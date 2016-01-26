<?php
/**
 * Register custom products positions for virtual categories into the search index
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_VirtualCategories
 * @author    Romain RUAUD <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualCategories_Model_Indexer_VirtualCategories_Product_Position extends Mage_Index_Model_Indexer_Abstract
{
    /**
     * Metadata view name, used to identify data related to this index
     */
    const METADATA_VIEW_NAME = "virtual_categories_product_position";

    /**
     * Metadata group code, used to identify data related to this index
     */
    const METADATA_GROUP_CODE = "virtual_categories_product_pos"; // Should be the same as indexer code, which is capped at 32 chars

    /**
     * Index match: product save, category save, store save
     * store group save
     *
     * @var array
     */
    protected $_matchedEntities = array(
        Mage_Catalog_Model_Product::ENTITY => array(
            Mage_Index_Model_Event::TYPE_SAVE,
            Mage_Index_Model_Event::TYPE_MASS_ACTION,
            Mage_Index_Model_Event::TYPE_DELETE
        ),
        Mage_Core_Model_Store::ENTITY => array(
            Mage_Index_Model_Event::TYPE_SAVE,
            Mage_Index_Model_Event::TYPE_DELETE
        ),
        Mage_Core_Model_Store_Group::ENTITY => array(
            Mage_Index_Model_Event::TYPE_SAVE
        ),
        Mage_Catalog_Model_Convert_Adapter_Product::ENTITY => array(
            Mage_Index_Model_Event::TYPE_SAVE
        ),
        Mage_Catalog_Model_Category::ENTITY => array(
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
        $category = $event->getDataObject();
        if ($event->getType() == Mage_Index_Model_Event::TYPE_SAVE) {
            if (Mage::helper('smile_elasticsearch')->isActiveEngine()) {
                $this->reindex($category);
            }
        }
    }

    /**
     * Reindex a single virtual category.
     *
     * @param Mage_Catalog_Model_Category $category The category.
     *
     * @return void
     */
    public function reindex($category)
    {
        /** Reindex all data from virtual categories products positions index */
        $engine       = Mage::helper('catalogsearch')->getEngine();
        $mapping      = $engine->getCurrentIndex()->getMapping('product');
        $dataprovider = $mapping->getDataProvider('virtual_categories_products_position');

        $dataprovider->updateAllData($category->getStoreId(), $category->getVirtualProductIds());
    }

    /**
     * Retrieve Indexer name
     *
     * @return string
     */
    public function getName()
    {
        return Mage::helper('smile_virtualcategories')->__('Custom products positions in Virtual Categories');
    }

    /**
     * Retrieve Indexer description
     *
     * @return string
     */
    public function getDescription()
    {
        return Mage::helper('smile_virtualcategories')->__('Computes custom positions for products in virtual categories.');
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
        $dataprovider = $mapping->getDataProvider('virtual_categories_products_position');
        $dataprovider->updateAllData();
    }
}