<?php
/**
 * Optimizer edit form boost and preview configuration tab
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
class Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Tab_Boost
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Tab specifics init.
     *
     * @return void
     */
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('smile/searchoptimizer/optimizer/edit/tab/boost.phtml');
    }

    /**
     * Prepare content for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('Boost configuration');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__('Boost configuration');
    }

    /**
     * Returns status flag about this tab can be showed or not
     *
     * @return true
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Returns status flag about this tab hidden or not
     *
     * @return true
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Prepare the form.
     *
     * @return Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Tab_Main
     */
    protected function _prepareForm()
    {
        $model = $this->getModel();
        $form = new Varien_Data_Form();
        $form->setHtmlIdPrefix('optimizer_');
        $model->prepareForm($form);
        $form->setValues($model->getData());
        $this->setForm($form);
        return parent::_prepareForm();
    }

    /**
     * Get currently edited optimizer.
     *
     * @return Smile_SearchOptimizer_Model_Optimizer
     */
    public function getModel()
    {
        return Mage::registry('search_optimizer');
    }

    /**
     * Return the JS prototype template of the preview URL.
     *
     * @return string
     */
    public function getPreviewUrlTemplate()
    {
        $urlParams = array('store_id' => '#{storeId}', 'query' => '#{query}', 'is_ajax' => true);

        if ($this->getModel() && $this->getModel()->getId()) {
            $urlParams['optimizer_id'] = $this->getModel()->getId();
        }

        return Mage::helper('adminhtml')->getUrl('*/search_optimizer/preview', $urlParams);
    }

    /**
     * Load store available for preview grouped by website and store group.
     *
     * @return array
     */
    public function getStorePreviewStoresOptions()
    {
        $result = array();
        $websites = Mage::app()->getWebsites();
        foreach ($websites as $website) {
            $result[$website->getId()] = array('name' => $website->getName());
            foreach ($website->getGroups() as $storeGroup) {
                $stores = array();
                foreach ($storeGroup->getStores() as $store) {
                    $stores[$store->getId()] = $store->getName();
                }
                if (!empty($store)) {
                    $group = array('name' => $storeGroup->getName(), 'stores' => $stores);
                    $result[$website->getId()]['groups'][$storeGroup->getId()] = $group;
                }
            }
        }

        return $result;
    }
}
