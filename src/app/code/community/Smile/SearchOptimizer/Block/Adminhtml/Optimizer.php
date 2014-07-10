<?php
/**
 * Optimizer list grid container
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
class Smile_SearchOptimizer_Block_Adminhtml_Optimizer extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    /**
     * Init container
     */
    public function __construct()
    {
        $this->_controller = 'adminhtml_optimizer';
        $this->_blockGroup = 'smile_searchoptimizer';
        $this->_headerText = Mage::helper('smile_searchoptimizer')->__('Search optimizers');
        $this->_addButtonLabel = Mage::helper('smile_searchoptimizer')->__('Add New Optimizer');
        parent::__construct();
    }

}
