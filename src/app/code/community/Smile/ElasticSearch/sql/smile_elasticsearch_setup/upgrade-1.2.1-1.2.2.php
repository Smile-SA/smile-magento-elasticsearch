<?php
/**
 * Append Missing foreign key to catalog_product_entity on search terms product positions
 * Also delete useless indexes previously created
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2016 Smile
 * @license   Apache License Version 2.0
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$tableName = $installer->getTable('smile_elasticsearch/search_term_product_position');

$installer->getConnection()->dropIndex(
    $tableName,
    $installer->getIdxName('smile_elasticsearch/search_term_product_position', array('query_id'))
);

$installer->getConnection()->dropIndex(
    $tableName,
    $installer->getIdxName('smile_elasticsearch/search_term_product_position', array('product_id'))
);

$installer->getConnection()->addForeignKey(
    $installer->getFkName(
        'smile_elasticsearch/search_term_product_position',
        'product_id',
        'catalog/product',
        'entity_id'
    ),
    $tableName,
    'product_id',
    $installer->getTable('catalog/product'),
    'entity_id',
    Varien_Db_Ddl_Table::ACTION_CASCADE,
    Varien_Db_Ddl_Table::ACTION_CASCADE
);

$installer->endSetup();