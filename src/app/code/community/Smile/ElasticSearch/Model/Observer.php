<?php
/**
 * Search observer
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
            if ($engine instanceof Smile_ElasticSearch_Model_Resource_Engine_ElasticSearch) {
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
        $engine = Mage::helper('catalogsearch')->getEngine();
        if ($engine instanceof Smile_ElasticSearch_Model_Resource_Engine_ElasticSearch) {
            $engine->installNewIndex();
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

    /**
      * Retrieve Fulltext Search instance
      *
      * @return Mage_CatalogSearch_Model_Fulltext
      */
    protected function _getIndexer()
    {
        return Mage::getSingleton('catalogsearch/fulltext');
    }

    /**
      * Fix category product indexing when product list changes for a category
      *
      * @param Varien_Event_Observer $observer Event data
      *
      * @return Smile_ElasticSearch_Model_Observer
      */
    public function reindexCategoryProduct(Varien_Event_Observer $observer)
    {
        $category = $observer->getEvent()->getCategory();
        $productIds = $category->getProductCollection()->getAllIds();
        $this->_getIndexer()->rebuildIndex(null, $observer->getEvent()->getProductIds())->resetSearchResults();
        Mage::dispatchEvent('smile_search_engine_reindex_category', array('category' => $category));
        return $this;
    }

    /**
     * Index category mapping setup
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Modyf_Search_Model_Observer
     */
    public function addCategoryMappingToIndex(Varien_Event_Observer $observer)
    {
        $indexProperties = $observer->getIndexProperties();
        $indexPropertiesData = $indexProperties->getData();
        $indexPropertiesData['body']['mappings']['category']['properties'] = array();
        $categoryMapping = &$indexPropertiesData['body']['mappings']['category']['properties'];
        $helper = Mage::helper('smile_elasticsearch');
        foreach (Mage::app()->getStores() as $store) {
            $languageCode = $helper->getLanguageCodeByStore($store);
            $categoryMapping[$helper->getSuggestFieldName($store)] = array(
                'type'     => 'completion',
                'payloads' => true,
                'max_input_length' => 500,
                'index_analyzer' => 'analyzer_' . $languageCode,
                'search_analyzer' => 'analyzer_' . $languageCode,
                'preserve_separators' => false
            );
        }

        $indexProperties->setData($indexPropertiesData);

        return $this;
    }

    /**
     * Reindex categories (auto suggest)
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Smile_ElasticSearch_Model_Observer
     */
    public function reindexCategories(Varien_Event_Observer $observer)
    {
        $engine = Mage::helper('catalogsearch')->getEngine();

        if ($engine instanceof Smile_ElasticSearch_Model_Resource_Engine_ElasticSearch) {

            foreach (Mage::app()->getStores() as $store) {
                $suggestFieldName = Mage::helper('smile_elasticsearch')->getSuggestFieldName($store);
                $result = array();

                $categories = Mage::getResourceModel('catalog/category_collection')
                    ->addAttributeToFilter('level', array('gt' => 1))
                    ->addAttributeToFilter('is_active', 1)
                    ->addAttributeToSelect('name')
                    ->setOrder('level', Varien_Data_Collection::SORT_ORDER_ASC);

                foreach ($categories as $category) {
                    $data = array(
                        'input'   => $category->getName(),
                        'output'  => $category->getName(),
                        'payload' => array('category_id' => $category->getId()),
                        'weight'  => $category->getLevel()
                    );

                    if (isset($result[$category->getParentId()])) {
                        $data['output'] = $result[$category->getParentId()][$suggestFieldName]['output'] . ' > ' . $data['output'];
                    }

                    $result[$category->getId()] = array(
                        'name'            => $category->getName(),
                        'id'              => $category->getId(),
                        'store_id'        => $store->getId(),
                        $suggestFieldName => $data
                    );
                }

                $engine->saveEntityIndexes($store->getId(), $result, 'category');
            }
        }

        return $this;
    }
}

