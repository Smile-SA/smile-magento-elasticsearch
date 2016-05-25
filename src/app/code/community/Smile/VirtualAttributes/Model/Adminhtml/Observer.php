<?php
/**
 * Virtual attributes adminhtml Observer
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
 * @package   Smile_VirtualAttributes
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2016 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualAttributes_Model_Adminhtml_Observer
{
    /**
     * Add virtual attributes types on Attribute Edit Form
     *
     * @param Varien_Event_Observer $observer The observer
     *
     * @event adminhtml_product_attribute_types
     *
     * @return Smile_VirtualAttributes_Model_Adminhtml_Observer
     */
    public function addVirtualAttributesTypes(Varien_Event_Observer $observer)
    {
        $response   = $observer->getEvent()->getResponse();
        $types      = $response->getTypes();
        $attributes = Mage::getConfig()->getNode('global/virtual_attributes_types')->asArray();

        foreach ($attributes as $attributeCode => $attributeOptions) {
            $types[] = array(
                'value'          => $attributeCode,
                'label'          => Mage::helper('smile_virtualattributes')->__($attributeOptions['label']),
                'hide_fields'    => isset($attributeOptions['hide_fields']) ? array_keys($attributeOptions['hide_fields']) : array(),
                'disabled_types' => isset($attributeOptions['disabled_types']) ? array_keys($attributeOptions['disabled_types']) : array()
            );
        }

        $response->setTypes($types);
    }

    /**
     * Ensure all dependencies processing when saving a virtual attribute
     *
     * @param Varien_Event_Observer $observer current observer
     *
     * @event catalog_entity_attribute_save_before
     *
     * @return Smile_VirtualAttributes_Model_Adminhtml_Observer
     */
    public function processAttributeTypeSave(Varien_Event_Observer $observer)
    {
        /** @var $attribute Mage_Eav_Model_Entity_Attribute_Abstract */
        $attribute = $observer->getEvent()->getAttribute();

        /** Detect if attribute is custom **/
        $attributes = Mage::getConfig()->getNode('global/virtual_attributes_types')->asArray();
        if (array_key_exists($attribute->getFrontendInput(), $attributes)) {
            $virtualAttributeConfig = $attributes[$attribute->getFrontendInput()];
            try {

                $attribute->setSourceModel(Smile_VirtualAttributes_Model_Catalog_Product_Attribute_Virtual::DEFAULT_SOURCE);
                /** Then set all needed values that comes from config.xml **/
                if (isset($virtualAttributeConfig['source_model'])) {
                    $attribute->setSourceModel($virtualAttributeConfig['source_model']);
                }
                if (isset($virtualAttributeConfig['frontend_model'])) {
                    $attribute->setFrontendModel($virtualAttributeConfig['frontend_model']);
                }
                if (isset($virtualAttributeConfig['backend_model'])) {
                    $attribute->setBackendModel($virtualAttributeConfig['backend_model']);
                }
                if (isset($virtualAttributeConfig['backend_type'])) {
                    $attribute->setBackendType($virtualAttributeConfig['backend_type']);
                }

                /** Finally call model class to build proper attribute **/
                Mage::getModel($attributes[$attribute->getFrontendInput()]['attribute_model'])->processAttributeSave($attribute);
            } catch (Exception $e) {
                Mage::logException($e);
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        return $this;
    }

    /**
     * Ensure all dependencies deletion when deleting a virtual attribute
     *
     * @param Varien_Event_Observer $observer current observer
     *
     * @event catalog_entity_attribute_delete_after
     *
     * @return Smile_VirtualAttributes_Model_Adminhtml_Observer
     */
    public function processAttributeTypeDeletion(Varien_Event_Observer $observer)
    {
        /** @var $object Mage_Eav_Model_Entity_Attribute_Abstract */
        $object = $observer->getEvent()->getAttribute();

        /** Detect if attribute is custom **/
        $attributes = Mage::getConfig()->getNode('global/virtual_attributes_types')->asArray();
        if (array_key_exists($object->getFrontendInput(), $attributes)) {
            try {
                /** Finally call model class to perform proper attribute deletion **/
                $attributeModel = $attributes[$object->getFrontendInput()]['attribute_model'];
                Mage::getModel($attributeModel)->processDeletion($object);
            } catch (Exception $e) {
                Mage::logException($e);
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

    }

    /**
     * Add the virtual attributes types to the filterable ones.
     *
     * @param Varien_Event_Observer $observer current observer
     *
     * @event smile_elasticsearch_get_searchable_attributes_frontend_inputs
     *
     * @return Smile_VirtualAttributes_Model_Adminhtml_Observer
     */
    public function setCustomAttributesFrontendInputsAsFilterable(Varien_Event_Observer $observer)
    {
        $attributeData  = $observer->getEvent()->getAttributeData();
        $frontendInputs = $attributeData->getFrontendInputs();

        $attributes = Mage::getConfig()->getNode('global/virtual_attributes_types')->asArray();
        $virtualFrontendInputs = array_keys($attributes);

        $observer->getEvent()->getAttributeData()->setFrontendInputs(array_merge($frontendInputs, $virtualFrontendInputs));

        return $this;
    }

    /**
     * Add custom element type for attributes form
     *
     * @param Varien_Event_Observer $observer current observer
     *
     * @event adminhtml_catalog_product_edit_element_types
     *
     * @return Smile_VirtualAttributes_Model_Adminhtml_Observer
     */
    public function updateElementTypes(Varien_Event_Observer $observer)
    {
        $response = $observer->getEvent()->getResponse();
        $types    = $response->getTypes();

        $attributes = Mage::getConfig()->getNode('global/virtual_attributes_types')->asArray();
        foreach ($attributes as $attributeFrontend => $attributeConfig) {
            if (!isset($attributeConfig['frontend_model']) || (is_null($attributeConfig['frontend_model']))) {
                $attributeConfig['frontend_model'] = Smile_VirtualAttributes_Model_Catalog_Product_Attribute_Virtual::DEFAULT_FRONTEND_MODEL;
            }
            $types[$attributeFrontend] = Mage::getConfig()->getBlockClassName($attributeConfig['frontend_model']);
        }

        $response->setTypes($types);

        return $this;
    }

    /**
     * Exclude virtual attributes from virtual categories rules creation.
     *
     * @param Varien_Event_Observer $observer The observer
     *
     * @event smile_elasticsearch_prepare_virtual_categories_attributes
     *
     * @return Smile_VirtualAttributes_Model_Adminhtml_Observer
     */
    public function excludeVirtualAttributesFromRules(Varien_Event_Observer $observer)
    {
        $attributes     = Mage::getConfig()->getNode('global/virtual_attributes_types')->asArray();
        $frontendInputs = array_keys($attributes);

        if (count($attributes)) {
            $observer->getSelect()->where(sprintf("main_table.frontend_input NOT IN ('%s')", implode(",'", $frontendInputs)));
        }

        return $this;
    }
}
