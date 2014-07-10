<?php
/**
 * Install tables for optimizer management.
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
 * Create table 'smile_searchoptimizer/optimizer'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('smile_searchoptimizer/optimizer'))
    ->addColumn(
        'optimizer_id',
        Varien_Db_Ddl_Table::TYPE_SMALLINT,
        null,
        array(
            'identity'  => true,
            'nullable'  => false,
            'primary'   => true,
        ),
        'Optimizer ID'
    )
    ->addColumn(
        'name',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array('nullable'  => false),
        'Optimizer Name'
    )
    ->addColumn(
        'is_active',
        Varien_Db_Ddl_Table::TYPE_SMALLINT,
        null,
        array(
            'nullable'  => false,
            'default'   => '1',
        ),
        'Is Optimizer Active'
    )
    ->addColumn(
        'model',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(),
        'Optimizer model'
    )
    ->addColumn(
        'config',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        '2M',
        array(),
        'Optimizer serialized configuration'
    )
    ->setComment('Serach optimizer Table');

$installer->getConnection()->createTable($table);

/**
 * Create table 'smile_searchoptimizer/optimizer_store'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('smile_searchoptimizer/optimizer_store'))
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
        'store_id',
        Varien_Db_Ddl_Table::TYPE_SMALLINT,
        null,
        array(
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ),
        'Store ID'
    )
    ->addIndex(
        $installer->getIdxName('smile_searchoptimizer/optimizer_store', array('store_id')),
        array('store_id')
    )
    ->addForeignKey(
        $installer->getFkName(
            'smile_searchoptimizer/optimizer_store', 'optimizer_id', 'smile_searchoptimizer/optimizer', 'optimizer_id'
        ),
        'optimizer_id',
        $installer->getTable('smile_searchoptimizer/optimizer'),
        'optimizer_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->addForeignKey(
        $installer->getFkName('smile_searchoptimizer/optimizer_store', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('CMS Block To Store Linkage Table');

$installer->getConnection()->createTable($table);

$installer->endSetup();
