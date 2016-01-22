<?php
/**
 * Index helper for Virtual Categories
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_VirtualCategories
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualCategories_Helper_Index extends Enterprise_Index_Helper_Data
{
    /**
     * Path to price indexer mode
     */
    const XML_PATH_LIVE_VIRTUAL_CATEGORIES_PRODUCT_POSITION_REINDEX_ENABLED
        = 'index_management/index_options/virtual_categories_products_position';

    /**
     * Retrieve products positions in search index mode
     *
     * @return boolean
     */
    public function isLiveProductPositionInVirtualCategoriesReindexEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_LIVE_VIRTUAL_CATEGORIES_PRODUCT_POSITION_REINDEX_ENABLED);
    }
}