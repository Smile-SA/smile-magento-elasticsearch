<?php
/**
 * ElasticSearch module setup : Make all categories anchor by default
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

$installer->updateAttribute(Mage_Catalog_Model_Category::ENTITY, 'is_anchor', 'frontend_input', 'hidden');
$installer->updateAttribute(Mage_Catalog_Model_Category::ENTITY, 'is_anchor', 'default_value', 1);

$installer->endSetup();
