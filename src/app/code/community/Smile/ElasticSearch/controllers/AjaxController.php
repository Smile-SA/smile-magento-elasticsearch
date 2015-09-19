<?php
/**
 * Autocomplete controller
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
require 'Mage/CatalogSearch/controllers/AjaxController.php';

/**
 * Autocomplete controller
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
class Smile_ElasticSearch_AjaxController extends Mage_CatalogSearch_AjaxController
{
    /**
     * Execute fulltext search autocomplete.
     *
     * @return Mage_Core_Controller_Front_Action Self reference
     */
    public function suggestAction()
    {
        if (Mage::helper('smile_elasticsearch/elasticsearch')->isActiveEngine()) {
            $this->loadLayout();
            $this->renderLayout();
        } else {
            parent::suggestAction();
        }
        return $this;
    }

    /**
     * Execute facet suggestion search autocomplete.
     *
     * @return Mage_Core_Controller_Front_Action Self reference
     */
    public function facetSuggestAction()
    {
        $suggestBlock = $this->getLayout()->createBlock('smile_elasticsearch/catalog_layer_filter_attribute_suggest');
        $this->getResponse()->setBody($suggestBlock->toHtml());
    }
}
