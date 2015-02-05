<?php
/**
 * This block handles variables displayed on all website pages : page type identifier and label
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
class Smile_Tracker_Block_Variables_Page_Base extends Smile_Tracker_Block_Variables_Page_Abstract
{
    const PAGE_LABELS_BY_IDENTIFER_MAP_CACHE_ID = 'smile_tracker_page_label_map';


    /**
     * Append the page type data to the tracked variables list
     *
     * @return array
     */
    public function getVariables()
    {
        return $this->getPageTypeInformations();
    }

    /**
     * List of the page type data
     *
     * @return array
     */
    public function getPageTypeInformations()
    {
        return array(
            'type.identifier' => $this->getPageTypeIdentifier(),
            'type.label'      =>  stripslashes($this->getPageTypeLabel()),
        );
    }

    /**
     * Page type identifier built from route (ex: catalog/product/view => catalog_product_view)
     *
     * @return string
     */
    public function getPageTypeIdentifier()
    {
        $request = $this->getRequest();
        return $request->getModuleName() . '_' . $request->getControllerName() . '_' . $request->getActionName();
    }

    /**
     * Human readable version of the page
     *
     * @return string
     */
    public function getPageTypeLabel()
    {
        if (!$this->getData('page_type_label')) {
            $label = '';
            $identifier = $this->getPageTypeIdentifier();
            $labelByIdentifier = $this->_getPageTypeLabelMap();

            if (isset($labelByIdentifier[$identifier])) {
                $label = $labelByIdentifier[$identifier];
            }

            $this->setData('page_type_label', $label);
        }

        return $this->getData('page_type_label');
    }

    /**
     * Return the array of page labels from layout indexed by handle names.
     *
     * @return array
     */
    protected function _getPageTypeLabelMap()
    {
        $labelByIdentifier = array();
        $cacheKey = self::PAGE_LABELS_BY_IDENTIFER_MAP_CACHE_ID . '_' . Mage::app()->getStore()->getId();
        $cache = Mage::app()->loadCache($cacheKey);

        if ($cache === false) {

            // Retrieve informations from design
            $design  = Mage::getDesign();
            $area    = $design->getArea();
            $package = $design->getPackageName();
            $theme   = $design->getTheme('layout');

            // Retrieve Layout Informations
            $layoutHandles = $this->getLayout()->getUpdate()->getFileLayoutUpdatesXml($area, $package, $theme);
            $layoutHandlesArr = $layoutHandles->xpath('/*/*/label/..');

            if ($layoutHandlesArr) {
                foreach ($layoutHandlesArr as $node) {
                    $identifier = $node->getName();
                    $helper = Mage::helper(Mage_Core_Model_Layout::findTranslationModuleName($node));
                    $labelByIdentifier[$identifier] = $this->helper('core')->jsQuoteEscape($helper->__((string) $node->label));
                }
            }

            $cacheTags = array(Mage_Core_Model_Layout_Update::LAYOUT_GENERAL_CACHE_TAG);
            Mage::app()->saveCache(
                serialize($labelByIdentifier), $cacheKey, $cacheTags, 7200
            );

        } else {
            $labelByIdentifier = unserialize($cache);
        }

        return $labelByIdentifier;
    }

}