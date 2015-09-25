<?php
/**
 * Search layer block implementation
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
class Smile_ElasticSearch_Block_Catalogsearch_Layer extends Smile_ElasticSearch_Block_Catalog_Layer_View
{
    /**
     * Indicates URL rewrites should be used for categories.
     *
     * @var boolean
     */
    protected $_usesUrlRewrite = false;

    /**
     * Returns current catalog layer.
     *
     * @return Smile_ElasticSearch_Model_Catalogsearch_Layer|Mage_Catalog_Model_Layer
     */
    public function getLayer()
    {
        /** @var $helper Smile_ElasticSearch_Helper_Data */
        $helper = Mage::helper('smile_elasticsearch');
        if ($helper->isActiveEngine()) {
            return Mage::getSingleton('smile_elasticsearch/catalogsearch_layer');
        }

        return parent::getLayer();
    }
}
