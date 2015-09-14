<?php
/**
 * Optimizer admin CRUD controller
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
class Smile_SearchOptimizer_Adminhtml_Search_OptimizerController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Init the controller environment (bradcrumbs, title, ...)
     *
     * @return Smile_SearchOptimizer_Adminhtml_Search_OptimizerController Self reference.
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('catalog/search')
            ->_addBreadcrumb(
                Mage::helper('smile_searchoptimizer')->__('Search'),
                Mage::helper('smile_searchoptimizer')->__('Search'),
                Mage::helper('smile_searchoptimizer')->__('Optimization')
            );
        return $this;
    }


    /**
     * Index action
     *
     * @return Smile_SearchOptimizer_Adminhtml_Search_OptimizerController Self reference.
     */
    public function indexAction()
    {
        $this->_title($this->__('smile_searchoptimizer'))->_title($this->__('Search optimizers'));

        $this->_initAction();
        $this->renderLayout();

        return $this;
    }

    /**
     * Create new CMS block
     *
     * @return Smile_SearchOptimizer_Adminhtml_Search_OptimizerController Self reference.
     */
    public function newAction()
    {
        if ($this->getRequest()->getParam('model')) {
            $layoutUpdates = array('default', 'adminhtml_search_optimizer_edit');
            $this->loadLayout($layoutUpdates);
            $this->_forward('edit');
        } else {
            $this->loadLayout();
            $this->renderLayout();
        }

        return $this;
    }

    /**
     * Edit CMS block
     *
     * @return Smile_SearchOptimizer_Adminhtml_Search_OptimizerController Self reference.
     */
    public function editAction()
    {
        $this->_title($this->__('smile_searchoptimizer'))->_title($this->__('Search optimizer'));

        // 1. Get ID and create model
        $id = $this->getRequest()->getParam('optimizer_id');
        $model = Mage::getModel('smile_searchoptimizer/optimizer');

        // 2. Initial checking
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $errorMsg = Mage::helper('smile_searchoptimizer')->__('This optimizer no longer exists.');
                Mage::getSingleton('adminhtml/session')->addError($errorMsg);
                $this->_redirect('*/*/');
                return;
            }
        } else {
            $model->setData('model', $this->getRequest()->getParam('model'));
        }

        $this->_title($model->getId() ? $model->getName() : $this->__('New optimizer'));

        // 3. Set entered data if was error when we do save
        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        // 4. Register model to use later in blocks
        Mage::register('search_optimizer', $model);

        // 5. Build edit form
        $breacrumb = $id ? Mage::helper('smile_searchoptimizer')->__('Edit optimizer')
                         : Mage::helper('smile_searchoptimizer')->__('New optimizer');
        $this->_initAction()
            ->_addBreadcrumb($breacrumb, $breacrumb)
            ->renderLayout();

        return $this;
    }

    /**
     * Save action
     *
     * @return Smile_SearchOptimizer_Adminhtml_Search_OptimizerController Self reference.
     */
    public function saveAction()
    {
        // check if data sent
        if ($data = $this->getRequest()->getPost()) {

            $id = $this->getRequest()->getParam('optimizer_id');
            $model = Mage::getModel('smile_searchoptimizer/optimizer')->load($id);
            if (!$model->getId() && $id) {
                $errorMsg = Mage::helper('smile_searchoptimizer')->__('This optimizer no longer exists.');
                Mage::getSingleton('adminhtml/session')->addError($errorMsg);
                $this->_redirect('*/*/');
                return;
            }

            // Check if a virtual rule is present in post and load it into the model
            if ($rule = $this->getRequest()->getParam('rule', false)) {
                if ($rule !== false) {
                    $ruleInstance = Mage::getModel('smile_virtualcategories/rule')->loadPost($rule);
                    $model->setFilterRule($ruleInstance);
                }
            }

            // init model and set data
            $data = $this->_filterDates($data, array('from_date', 'to_date'));
            $model->setData($data);

            // try to save it
            try {
                // save the data
                $model->save();
                // display success message
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('smile_searchoptimizer')->__('The block has been saved.')
                );
                // clear previously saved data from session
                Mage::getSingleton('adminhtml/session')->setFormData(false);

                // check if 'Save and Continue'
                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', array('optimizer_id' => $model->getId()));
                    return;
                }
                // go to grid
                $this->_redirect('*/*/');
                return;

            } catch (Exception $e) {
                // display error message
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                // save data in session
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                // redirect to edit form
                $this->_redirect('*/*/edit', array('optimizer_id' => $this->getRequest()->getParam('optimizer_id')));
                return;
            }
        }
        $this->_redirect('*/*/');
    }

    /**
     * Delete action
     *
     * @return Smile_SearchOptimizer_Adminhtml_Search_OptimizerController Self reference.
     */
    public function deleteAction()
    {
        // check if we know what should be deleted
        if ($id = $this->getRequest()->getParam('optimizer_id')) {
            try {
                // init model and delete
                $model = Mage::getModel('smile_searchoptimizer/optimizer');
                $model->load($id);
                $model->delete();
                // display success message
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('smile_searchoptimizer')->__('The optimizer has been deleted.')
                );
                // go to grid
                $this->_redirect('*/*/');
                return;

            } catch (Exception $e) {
                // display error message
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                // go back to edit form
                $this->_redirect('*/*/edit', array('optimizer_id' => $id));
                return;
            }
        }
        // display error message
        Mage::getSingleton('adminhtml/session')->addError(
            Mage::helper('smile_searchoptimizer')->__('Unable to find a block to delete.')
        );
        // go to grid
        $this->_redirect('*/*/');

        return $this;
    }

    /**
     * Preview optmizer action
     *
     * @return Smile_SearchOptimizer_Adminhtml_Search_OptimizerController Self reference.
     */
    public function previewAction()
    {
        $data = $this->getRequest()->getPost();

        if ($data) {
            $id = $this->getRequest()->getParam('optimizer_id');
            $model = Mage::getModel('smile_searchoptimizer/optimizer')->load($id);

            // Check if a virtual rule is present in post and load it into the model
            if ($rule = $this->getRequest()->getParam('rule', false)) {
                if ($rule !== false) {
                    $ruleInstance = Mage::getModel('smile_virtualcategories/rule')->loadPost($rule);
                    $ruleInstance->setStoreId($this->getRequest()->getParam('store_id'));
                    $model->setFilterRule($ruleInstance);
                }
            }

            // init model and set data
            $data = $this->_filterDates($data, array('from_date', 'to_date'));
            $model->setData($data);

            Mage::register('current_optimizer', $model);
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Check the permission to run it
     *
     * @return boolean
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/search/optimizer');
    }
}