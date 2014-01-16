<?php
/**
 * Search observer.
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
class Smile_ElasticSearch_Model_Observer
{
    /**
     * Adds search weight parameter in attribute form.
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Smile_ElasticSearch_Model_Observer Self Reference
     */
    public function eavAttributeEditFormInit(Varien_Event_Observer $observer)
    {
        /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        $attribute = $observer->getEvent()->getAttribute();
        $form = $observer->getEvent()->getForm();
        $fieldset = $form->getElement('front_fieldset');

        $fieldset->addField(
            'search_weight',
            'select', array(
                'name' => 'search_weight',
                'label' => Mage::helper('smile_elasticsearch')->__('Search Weight'),
                'values' => array(
                    1 => 1,
                    2 => 2,
                    3 => 3,
                    4 => 4,
                    5 => 5
                ),
                ),
            'is_searchable'
        );

        if ($attribute->getAttributeCode() == 'name') {
            $form->getElement('is_searchable')->setDisabled(1);
        }

        return $this;
    }

    /**
     * Requires catalog search indexation.
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Smile_ElasticSearch_Model_Observer Self Reference
     */
    public function requireCatalogsearchReindex(Varien_Event_Observer $observer)
    {
        if (Mage::helper('smile_elasticsearch')->isActiveEngine()) {
            /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            $attribute = $observer->getEvent()->getAttribute();
            if ($attribute->getData('search_weight') != $attribute->getOrigData('search_weight')) {
                Mage::getSingleton('index/indexer')->getProcessByCode('catalogsearch_fulltext')
                    ->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
            }
        }

        return $this;
    }

    /**
     * Deletes index if full catalog search reindexation is asked.
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Smile_ElasticSearch_Model_Observer Self Reference
     */
    public function beforeIndexProcessStart(Varien_Event_Observer $observer)
    {
        $storeId = $observer->getEvent()->getStoreId();
        $productIds = $observer->getEvent()->getProductIds();
        if (null === $storeId && null === $productIds) {
            $engine = Mage::helper('catalogsearch')->getEngine();
            if ($engine instanceof Smile_ElasticSearch_Model_Resource_Engine_Abstract) {
                $engine->prepareNewIndex();
            }
        }

        return $this;
    }

    /**
     * Install new index when indexation is finished
     *
     * @param Varien_Event_Observer $observer $observer Event data
     *
     * @return Smile_ElasticSearch_Model_Observer Self Reference
     */
    public function installIndex(Varien_Event_Observer $observer)
    {
        $storeId = $observer->getEvent()->getStoreId();
        $productIds = $observer->getEvent()->getProductIds();

        if (null === $storeId && null === $productIds) {
            $engine = Mage::helper('catalogsearch')->getEngine();
            if ($engine instanceof Smile_ElasticSearch_Model_Resource_Engine_Abstract) {
                $engine->installNewIndex();
            }
        }

        return $this;
    }

    /**
     * Reset search engine if it is enabled for catalog navigation
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Smile_ElasticSearch_Model_Observer Self Reference
     */
    public function resetCurrentCatalogLayer(Varien_Event_Observer $observer)
    {
        if (Mage::helper('smile_elasticsearch')->isActiveEngine()) {
            Mage::register('current_layer', Mage::getSingleton('smile_elasticsearch/catalog_layer'));
        }

        return $this;
    }

    /**
     * Reset search engine if it is enabled for search navigation
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Smile_ElasticSearch_Model_Observer Self Reference
     */
    public function resetCurrentSearchLayer(Varien_Event_Observer $observer)
    {
        if (Mage::helper('smile_elasticsearch')->isActiveEngine()) {
            Mage::register('current_layer', Mage::getSingleton('smile_elasticsearch/catalogsearch_layer'));
        }

        return $this;
    }

}

