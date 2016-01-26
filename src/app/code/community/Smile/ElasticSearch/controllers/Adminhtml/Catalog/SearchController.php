<?php
require 'Mage/Adminhtml/controllers/Catalog/SearchController.php';
/**
 * Extended catalogsearch controller to manage preview for search terms, and saving of enhanced products positions
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
 * @package   Smile_ElasticSearch
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Adminhtml_Catalog_SearchController extends Mage_Adminhtml_Catalog_SearchController
{
    /**
     * Provide a preview for a given search term, on a specific store
     *
     * @return void Nothing (render layout)
     */
    public function previewAction()
    {
        $data       = $this->getRequest()->getPost();
        $queryId    = $this->getRequest()->getParam('id', null);

        if ($this->getRequest()->isPost() && $data) {
            /* @var $model Mage_CatalogSearch_Model_Query */
            $model = Mage::getModel('catalogsearch/query');

            $queryText  = $this->getRequest()->getParam('query_text', false);
            if ($queryText !== false) {
                $queryText = Mage::helper("core")->urlDecode($queryText);
            }

            $storeId    = $this->getRequest()->getParam('store_id', false);

            if ($queryText) {
                $model->setStoreId($storeId);
                $model->loadByQueryText($queryText);
                if ($model->getId() && $model->getId() != $queryId) {
                    Mage::throwException(
                        Mage::helper('catalog')->__('Search Term with such search query already exists.')
                    );
                } else if (!$model->getId() && $queryId) {
                    $model->load($queryId);
                }
            } else if ($queryId) {
                $model->load($queryId);
            }

            Mage::register('current_catalog_search', $model);
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Save search query
     *
     * Overriden but just to add confirmation message and permits the user to get back to current term edition
     *
     * @return void Nothing
     */
    public function saveAction()
    {
        $hasError   = false;
        $data       = $this->getRequest()->getPost();
        $queryId    = $this->getRequest()->getPost('query_id', null);

        if ($this->getRequest()->isPost() && $data) {
            /* @var $model Mage_CatalogSearch_Model_Query */
            $model = Mage::getModel('catalogsearch/query');

            // validate query
            $queryText  = $this->getRequest()->getPost('query_text', false);
            $storeId    = $this->getRequest()->getPost('store_id', false);

            try {
                if ($queryText) {
                    $model->setStoreId($storeId);
                    $model->loadByQueryText($queryText);
                    if ($model->getId() && $model->getId() != $queryId) {
                        Mage::throwException(
                            Mage::helper('catalog')->__('Search Term with such search query already exists.')
                        );
                    } else if (!$model->getId() && $queryId) {
                        $model->load($queryId);
                    }
                } else if ($queryId) {
                    $model->load($queryId);
                }

                $model->addData($data);
                $model->setIsProcessed(0);

                $model->save();

            } catch (Mage_Core_Exception $exception) {
                $this->_getSession()->addError($exception->getMessage());
                $hasError = true;
            } catch (Exception $exception) {
                $this->_getSession()->addException(
                    $exception,
                    Mage::helper('catalog')->__('An error occurred while saving the search query.')
                );
                $hasError = true;
            }
        }

        if ($hasError) {
            $this->_getSession()->setPageData($data);
            $this->_redirect('*/*/edit', array('id' => $queryId));
        } else {
            $this->_getSession()->addSuccess(
                Mage::helper('smile_elasticsearch')->__('Search Term has been saved.')
            );

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', array('id' => $queryId));
                return;
            }

            $this->_redirect('*/*');
        }
    }
}