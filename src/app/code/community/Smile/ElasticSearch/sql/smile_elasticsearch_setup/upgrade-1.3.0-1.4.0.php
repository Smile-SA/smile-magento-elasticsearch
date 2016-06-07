<?php
/**
 * Set the "used_in_product_search" attribute as non-required.
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

$installer->updateAttribute($entityTypeId, "used_in_product_search", "is_required", 0);

$installer->endSetup();
