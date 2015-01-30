<?php
/**
 * This block handles catalog related variables displayed on all website pages when available :
 *
 *   - current category
 *   - current product
 *   - product list content and filters applied
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_Tracker
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_Tracker_Block_Variables_Page_Catalog extends Smile_Tracker_Block_Variables_Page_Abstract
{
    /**
     * Returnn the list of catalog related variables.
     *
     * @return array
     */
    public function getVariables()
    {
        $variables = array_merge(
            $this->getCategoryVariables(),
            $this->getProductVariables(),
            $this->getLayerVariables()
        );

        return $variables;
    }

    /**
     * Returns categories variables (id, name and path).
     *
     * @return array
     */
    public function getCategoryVariables()
    {
        $variables = array();

        if (Mage::registry('current_category')) {
            $category = Mage::registry('current_category');
            $variables['category.id']    = $category->getId();
            $variables['category.label'] = $category->getName();
            $variables['category.path']  = $category->getPath();
        }

        return $variables;
    }

    /**
     * Return list of the product relatedd variables (id, label, sku)
     *
     * @return array
     */
    public function getProductVariables()
    {
        $variables = array();

        if (Mage::registry('current_product')) {
            $product = Mage::registry('current_product');
            $variables['product.id'] = $product->getId();
            $variables['product.label'] = $product->getName();
            $variables['product.sku'] = $product->getSku();
        }
        return $variables;
    }

    /**
     * Return list of product list variables (pages, sort, display mode, filters)
     *
     * @return array
     */
    public function getLayerVariables()
    {
        $variables = array();

        $productListBlock = $this->getLayout()->getBlock('product_list_toolbar');

        if ($productListBlock && $productListBlock->getCollection()) {
            $variables['product_list.page_count'] = $productListBlock->getLastPageNum();
            $variables['product_list.product_count'] = $productListBlock->getTotalNum();
            $variables['product_list.current_page'] = $productListBlock->getCurrentPage();
            $variables['product_list.sort_order'] = $productListBlock->getCurrentOrder();
            $variables['product_list.sort_direction'] = $productListBlock->getCurrentDirection();
            $variables['product_list.display_mode'] = $productListBlock->getCurrentMode();
        }

        $layer = Mage::registry('current_layer');

        if ($layer) {
            $filters = array();
            $layerState = $layer->getState();
            foreach ($layerState->getFilters() as $currentFilter) {
                $currentFilterBase = $currentFilter->getFilter() ? $currentFilter->getFilter() : $currentFilter;
                $identifier = $currentFilter->getRequestVar();

                if ($currentFilter->getFilter()) {
                    $identifier = $currentFilter->getFilter()->getRequestVar();
                }

                $filterValue = $this->getRequest()->getParam($identifier, '');

                if (is_array($filterValue)) {
                    $filterValue = implode('|', $filterValue);
                }

                $variables['product_list.filters.' . $identifier] = $filterValue;
            }
        }

        return $variables;
    }

}