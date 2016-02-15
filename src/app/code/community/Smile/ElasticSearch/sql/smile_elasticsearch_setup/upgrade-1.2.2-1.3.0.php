<?php
/**
 * Append an attribute to all categories, to decide if their name should be used for fulltext indexation or not
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
 * @author    Romain RUAUD <romain.ruaud@smile.fr>
 * @copyright 2016 Smile
 * @license   Apache License Version 2.0
 */

/**
 * @var Mage_Catalog_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

$entityTypeId          = $installer->getEntityTypeId('catalog_category');
$defaultAttributeSetId = $this->getDefaultAttributeSetId($entityTypeId);
$defaultGroup          = $this->getAttributeGroup($entityTypeId, $defaultAttributeSetId, 'General Information');

$installer->addAttribute(
    $entityTypeId,
    'used_in_product_search',
    array(
        'type'       => 'int',
        'label'      => 'Use category name in product search',
        'input'      => 'select',
        'source'     => 'eav/entity_attribute_source_boolean',
        'global'     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'required'   => true,
        'default'    => 1,
        'enabled'    => true,
        'is_enabled' => true,
        'visible'    => true,
        'note'       => "If the category name is used for fulltext search on products.",
        'sort_order' => 150
    )
);

$installer->addAttributeToSet(
    $entityTypeId,
    $defaultAttributeSetId,
    $defaultGroup['attribute_group_id'],
    'used_in_product_search'
);

$attributeId = $installer->getAttributeId($entityTypeId, 'used_in_product_search');

$select = $installer->getConnection()->select();

$select->from(
    $installer->getTable('catalog_category_entity'),
    array(
        new Zend_Db_Expr("{$entityTypeId} as entity_type_id"),
        new Zend_Db_Expr("{$attributeId} as attribute_id"),
        'entity_id',
        new Zend_Db_Expr("1 as value")
    )
);

$insert = $installer->getConnection()->insertFromSelect(
    $select,
    $installer->getTable('catalog_category_entity_int'),
    array('entity_type_id', 'attribute_id', 'entity_id', 'value')
);

$installer->getConnection()->query($insert);

$installer->endSetup();
