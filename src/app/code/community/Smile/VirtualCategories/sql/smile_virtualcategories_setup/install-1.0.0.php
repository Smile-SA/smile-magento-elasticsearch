<?php
/**
 * Virtual categories module setup
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
 * @package   Smile_VirtualCategories
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */

/**
 * @var Mage_Catalog_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$entityTypeId          = $installer->getEntityTypeId('catalog_category');
$defaultAttributeSetId = $this->getDefaultAttributeSetId($entityTypeId);
$defaultGroup          = $this->getAttributeGroup($entityTypeId, $defaultAttributeSetId, 'General Information');

$installer->addAttribute(
    $entityTypeId,
    'virtual_category',
    array(
        'type'                      => 'text',
        'label'                     => 'Virtual category configuration',
        'enabled'                   => true,
        'is_enabled'                => true,
        'input'                     => 'hidden',
        'global'                    => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'required'                  => false,
        'visible'                   => true,
        'backend'                   => 'smile_virtualcategories/category_attributes_backend_virtual'
    )
);

$installer->addAttributeToSet($entityTypeId, $defaultAttributeSetId, $defaultGroup['attribute_group_id'], 'virtual_category', 200);

$installer->endSetup();
