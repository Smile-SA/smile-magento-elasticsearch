<?php
/**
 * ElasticSearch module setup :
 *
 *   - Append rating has filterable and sortable attribute => using a "virtual attribute"
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

/**
 * @var Mage_Catalog_Model_Resource_Setup
 */

$installer = $this;
$installer->startSetup();

$productEntityTypeId   = $installer->getEntityTypeId('catalog_product');
$defaultAttributeSetId = $this->getDefaultAttributeSetId($productEntityTypeId);
$defaultGroupId        = $this->getDefaultAttributeGroupId($productEntityTypeId);

$installer->addAttribute(
    $productEntityTypeId,
    'rating_filter',
    array(
        'type'                      => 'decimal',
        'label'                     => 'Rating filter',
        'enabled'                   => true,
        'is_enabled'                => true,
        'input'                     => 'hidden',
        'global'                    => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'required'                  => false,
        'visible'                   => 1,
        'searchable'                => 1,
        'filterable_in_search'      => 1,
        'visible_in_advanced_search'=> 0,
        'filterable'                => 1,
        'comparable'                => 0,
        'is_configurable'           => 0,
        'used_in_product_listing'   => 0,
        'user_defined'              => 0,
        'visible_on_front'          => 1,
        'is_html_allowed_on_front'  => 0,
        'is_used_for_price_rules'   => 0,
        'used_for_promo_rules'      => 0,
        'used_for_sort_by'          => 1,
        'is_configurable'           => 0,
        'position'                  => 0,
    )
);

$installer->addAttributeToSet($productEntityTypeId, $defaultAttributeSetId, $defaultGroupId, 'rating_filter', 200);

$installer->endSetup();
