<?php
/**
 * Display a boost score analysys tab into admin product view.
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
class Smile_SearchOptimizer_Block_Adminhtml_Catalog_Product_Edit_Tab_Score
  extends Mage_Adminhtml_Block_Widget implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Set the template at construct time.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('smile/searchoptimizer/catalog/product/edit/tab/score.phtml');
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('Search optimizers');
    }

    /**
     * Return Tab title
     *
     * @return string
    */
    public function getTabTitle()
    {
        return $this->__('Search optimizers analysis');
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
    */
    public function canShowTab()
    {
        return Mage::helper('smile_elasticsearch')->isActiveEngine();
    }

    /**
     * Tab is hidden
     *
     * @return boolean
    */
    public function isHidden()
    {
        return false;
    }

    /**
     * Retrieve the current product
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        return Mage::registry('current_product');
    }

    /**
     * List of the optimizer with scores applied to the product..
     *
     * @return Smile_SearchOptimizer_Model_Resource_Optimizer_Collection
     */
    public function getOptimizers()
    {
        $productId = $this->getProduct()->getId();
        $storeId = $this->getProduct()->getStoreId();

        if ($storeId == 0) {
            $storeId = Mage::app()->getDefaultStoreView()->getId();
        }

        return Mage::getModel('smile_searchoptimizer/percolator')->analyzeOptmizers($productId, $storeId);
    }

    /**
     * Display the boost value is percent.
     *
     * @param number $score The optimizer value.
     *
     * @return number
     */
    public function getBoostInPercent($score)
    {
        return round(($score - 1) * 100, 2);
    }

    /**
     * Return 'yes' or 'no' if the optmizer is applied or not.
     *
     * @param Smile_SearchOptimizer_Model_Resource_Optimizer $optimizer The optimizer.
     *
     * @return string
     */
    public function getIsAppliedLabel($optimizer)
    {
        return $optimizer->getOptimizedScore() ? $this->__('Yes') : $this->__('No');
    }

    /**
     * Return a summary of all boost applied to the product by query type.
     *
     * @return array
     */
    public function getSummaryByQueryType()
    {
        $queryTypeSource = Mage::getModel('smile_searchoptimizer/adminhtml_system_source_queryType');
        $summary = array();
        foreach ($queryTypeSource->toOptionArray(true) as $currentOption) {
            $summary[$currentOption['value']] = array('label' => $currentOption['label'], 'score' => 1);
        }
        foreach ($this->getOptimizers() as $optimizer) {
            $queryTypes = $optimizer->getQueryType();

            foreach ($queryTypes  as $queryType) {
                if ($optimizer->getOptimizedScore()) {
                    $summary[$queryType]['score'] = $summary[$queryType]['score'] * $optimizer->getOptimizedScore();
                }
            }
        }
        return $summary;
    }

    /**
     * Get edit URL for the optimizer.
     *
     * @param Smile_SearchOptimizer_Model_Resource_Optimizer $optimizer The optimizer.
     *
     * @return string
     */
    public function getOptimizerEditUrl($optimizer)
    {
        return Mage::helper('adminhtml')->getUrl('*/search_optimizer/edit', array('optimizer_id' => $optimizer->getId()));
    }

    /**
     * Get the URL of the optimizers list.
     *
     * @return string
     */
    public function getOptimizerListUrl()
    {
        return Mage::helper('adminhtml')->getUrl('*/search_optimizer/index');
    }

    /**
     * Translate query type to human readable label.
     *
     * @param string $value The query type identifier
     *
     * @return string
     */
    public function getQueryTypeLabel($value)
    {
        $label = $value;
        $queryTypeSource = Mage::getModel('smile_searchoptimizer/adminhtml_system_source_queryType');
        foreach ($queryTypeSource->toOptionArray(true) as $currentOption) {
            if ($currentOption['value'] == $value) {
                $label = $currentOption['label'];
            }
        }
        return $label;
    }
}