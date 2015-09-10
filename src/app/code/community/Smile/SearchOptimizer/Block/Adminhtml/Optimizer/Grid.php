<?php
/**
 * Optimizer admin grid
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
class Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    /**
     * Grid construct method construct method.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('searchOptimizerGrid');
        $this->setDefaultSort('name');
        $this->setDefaultDir('ASC');
    }

    /**
     * Init the optimize collection for the grid
     *
     * @return Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Grid Self reference
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('smile_searchoptimizer/optimizer')->getCollection();
        /* @var $collection Mage_Cms_Model_Mysql4_Block_Collection */
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Append columns to the grid
     *
     * @return Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Grid Self reference
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'name',
            array(
                'header'    => Mage::helper('smile_searchoptimizer')->__('Name'),
                'align'     => 'left',
                'index'     => 'name',
            )
        );

        $this->addColumn(
            'model',
            array(
                'header'    => Mage::helper('smile_searchoptimizer')->__('Model'),
                'align'     => 'left',
                'index'     => 'model',
                'type'      => 'options',
                'options'   => Mage::getSingleton('smile_searchoptimizer/optimizer')->getAvailableModels()
            )
        );

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn(
                'store_id',
                array(
                    'header'        => Mage::helper('smile_searchoptimizer')->__('Store View'),
                    'index'         => 'store_id',
                    'type'          => 'store',
                    'store_all'     => true,
                    'store_view'    => true,
                    'sortable'      => false,
                    'filter_condition_callback' => array($this, '_filterStoreCondition'),
                )
            );
        }

        $this->addColumn(
            'is_active',
            array(
                'header'    => Mage::helper('smile_searchoptimizer')->__('Status'),
                'index'     => 'is_active',
                'type'      => 'options',
                'options'   => array(
                    0 => Mage::helper('smile_searchoptimizer')->__('Disabled'),
                    1 => Mage::helper('smile_searchoptimizer')->__('Enabled')
                ),
            )
        );

        return parent::_prepareColumns();
    }

    /**
     * Apply store filter to the collection.
     *
     * @param Smile_SearchOptimizer_Model_Resource_Optimizer_Collection $collection Collection to filter
     * @param Mage_Adminhtml_Block_Widget_Grid_Column                   $column     Store column
     *
     * @return Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Grid Self reference
     */
    protected function _filterStoreCondition($collection, $column)
    {
        if (!$value = $column->getFilter()->getValue()) {
            return;
        }

        $collection->addStoreFilter($value);

        return $this;
    }

    /**
     * Row click url
     *
     * @param Smile_SearchOptimizer_Model_Optimizer $row Object representing the row
     *
     * @return string
     */
    public function getRowUrl($row)
    {

        return $this->getUrl('*/*/edit', array('optimizer_id' => $row->getId()));
    }

}
