<?php
/**
 * Optimizer edit form tabs.
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
class Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{

    /**
     * Init tabs
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('smile_search_optimizer_edit_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle($this->__('Edit optimizer'));
    }
}
