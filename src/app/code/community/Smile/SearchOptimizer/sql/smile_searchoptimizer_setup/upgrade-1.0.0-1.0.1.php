<?php
/**
 * Add query type selector for optimizers
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
 * @package   Smile_SearchOptimizer
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2014 Smile
 * @license   Apache License Version 2.0
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

/**
 * Create table 'smile_searchoptimizer/optimizer_querytype'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('smile_searchoptimizer/optimizer_querytype'))
    ->addColumn(
        'optimizer_id',
        Varien_Db_Ddl_Table::TYPE_SMALLINT,
        null,
        array(
            'nullable'  => false,
            'primary'   => true,
        ),
        'Optimizer ID'
    )
    ->addColumn(
        'query_type',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable'  => false,
            'primary'   => true
        ),
        'Query type'
    )
    ->addIndex(
        $installer->getIdxName('smile_searchoptimizer/optimizer_querytype', array('query_type')),
        array('query_type')
    )
    ->addForeignKey(
        $installer->getFkName(
            'smile_searchoptimizer/optimizer_querytype', 'optimizer_id', 'smile_searchoptimizer/optimizer', 'optimizer_id'
        ),
        'optimizer_id',
        $installer->getTable('smile_searchoptimizer/optimizer'),
        'optimizer_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('Query type per optimizer table');

$installer->getConnection()->createTable($table);

$installer->endSetup();
