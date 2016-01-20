<?php
/**
 * Add a table dedicated to contain specific boosted products positions for search terms
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
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

/**
 * Create table 'smile_elasticsearch/search_term_product_position'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('smile_elasticsearch/search_term_product_position'))
    ->addColumn(
        'query_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        array(
            'nullable'  => false,
            'primary'   => true,
        ),
        'Query ID'
    )
    ->addColumn(
        'product_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        255,
        array(
            'nullable'  => false,
            'primary'   => true
        ),
        'Product Id'
    )
    ->addColumn(
        'position',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        255,
        array(
            'nullable'  => false,
        ),
        'Product Position'
    )
    ->addIndex(
        $installer->getIdxName('smile_elasticsearch/search_term_product_position', array('query_id')),
        array('query_id')
    )
    ->addIndex(
        $installer->getIdxName('smile_elasticsearch/search_term_product_position', array('product_id')),
        array('product_id')
    )
    ->addForeignKey(
        $installer->getFkName(
            'smile_elasticsearch/search_term_product_position',
            'query_id',
            'catalogsearch/search_query',
            'query_id'
        ),
        'query_id',
        $installer->getTable('catalogsearch/search_query'),
        'query_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('Products positions per query table');

$installer->getConnection()->createTable($table);

$installer->endSetup();
