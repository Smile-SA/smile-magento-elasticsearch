<?php
/**
 * Optimizer edit form.
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
class Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{

    /**
     * Init form
     *
     * @return void.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('optimizer_form');
        $this->setTitle(Mage::helper('smile_searchoptimizer')->__('Optimizer Information'));
    }

    /**
     * Create the form and append field to it.
     *
     * @return Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Form
     */
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array('id' => 'edit_form', 'action' => $this->getData('action'), 'method' => 'post'));
        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }

}
