<?php
require 'Mage/Adminhtml/controllers/Catalog/CategoryController.php';
/**
 * Adminhtml controller for virtual categories
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_VirtualCategories
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualCategories_Adminhtml_Catalog_CategoryController
    extends Mage_Adminhtml_Catalog_CategoryController
{
    /**
     * Obtain a preview for a given virtual category
     *
     * @return void Nothing
     */
    public function previewAction()
    {
        $data = $this->getRequest()->getPost();

        if ($data) {
            $categoryId = $this->getRequest()->getParam('category_id');
            $model      = Mage::getModel('catalog/category')->load($categoryId);
            $storeId    = $this->getRequest()->getParam('store', false);

            if ($storeId) {
                $model->setStoreId($storeId);
            }

            // Check if a virtual rule is present in post and load it into the model
            if ($rule = $this->getRequest()->getParam('rule', false)) {
                if ($rule !== false) {

                    if (is_string($rule) && (Mage::helper("core")->jsonDecode($rule) !== null)) {
                        $rule = Mage::helper("core")->jsonDecode($rule);
                    }

                    $ruleInstance = Mage::getModel('smile_virtualcategories/rule')->loadPost($rule);
                    $ruleInstance->setStoreId($this->getRequest()->getParam('store_id'));
                    $ruleInstance->setCategory($model);
                    $model->setVirtualRulePreview($ruleInstance);
                }
            }

            Mage::register('current_category', $model);
        }

        $this->loadLayout();
        $this->renderLayout();
    }
}