<?php
/**
 * Add date filter on optimizers
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

$table = $installer->getTable('smile_searchoptimizer/optimizer');
$columnDefinitions = array(
    'from_date' => array(
        'type'     => Varien_Db_Ddl_Table::TYPE_DATE,
        'nullable' => true,
        'comment'  => 'Enable rule from date',
    ),
    'to_date' => array(
        'type'     => Varien_Db_Ddl_Table::TYPE_DATE,
        'nullable' => true,
        'comment'  => 'Enable rule to date',
    )
);

foreach ($columnDefinitions as $colName => $definition) {
    $installer->getConnection()->addColumn($table, $colName, $definition);
}

$installer->endSetup();
