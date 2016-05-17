<?php
/**
 * Virtual categories "root category" renderer
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
 * @package   Smile_VirtualCategories
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2016 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualCategories_Block_Adminhtml_Catalog_Category_Tab_Product_Renderer_RootCategory
    extends Mage_Core_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * Render category chooser.
     *
     * @param Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $htmlId = $element->getHtmlId();

        $html = '<td class="label"><label for="' . $htmlId . '">' . $element->getLabel() . '</label></td>';

        if ($element->getTooltip()) {
            $html .= '<td class="value with-tooltip">';
            $html .= $this->_getElementHtml($element);
            $html .= '<div class="field-tooltip"><div>' . $element->getTooltip() . '</div></div>';
        } else {
            $html .= '<td class="value">';
            $html .= $this->_getElementHtml($element);
        };

        if ($element->getComment()) {
            $html.= '<p class="note"><span>' . $element->getComment() . '</span></p>';
        }

        $html.= '</td>';

        return $this->_decorateRowHtml($element, $html);
    }

    /**
     * Preparing global layout.
     * Adds the required JS and CSS elements to open the cms category selector in an overlay
     *
     * @return Mage_Core_Block_Abstract
     */
    protected function _prepareLayout()
    {
        $this->getLayout()->getBlock('head')->addJs('mage/adminhtml/wysiwyg/widget.js');
        $this->getLayout()->getBlock('head')->addItem('js', 'prototype/window.js');
        $this->getLayout()->getBlock('head')->addItem('js_css', 'prototype/windows/themes/default.css');
        $this->getLayout()->getBlock('head')->addCss('lib/prototype/windows/themes/magento.css');
        $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);

        return parent::_prepareLayout();
    }

    /**
     * Return the HTML of the form element/field
     *
     * @param Varien_Data_Form_Element_Abstract $element Form element to return the HTML for
     *
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $configData = array(
            'button' => array(
                'open' => 'Select category ...'
            )
        );

        // Standard Category chooser is messing up the form prefix.
        $originalPrefix = $element->getForm()->getHtmlIdPrefix();
        if ($element->getForm()->getHtmlIdPrefix()) {
            $element->getForm()->setHtmlIdPrefix("");
        }

        /** @var $categoryChooser Mage_Adminhtml_Block_Catalog_Category_Widget_Chooser */
        $categoryChooser = Mage::getBlockSingleton('adminhtml/catalog_category_widget_chooser');
        $categoryChooser->setConfig($configData)
            ->setFieldsetId($element->getFieldsetId())
            ->setId($element->getId());
        // prepareElementHtml stores the widget rendering into the $element's after_element_html
        $categoryChooser->prepareElementHtml($element);

        $chooserControl = $element->getForm()->getElement('chooser' . $element->getId());

        $html = $element->getAfterElementHtml() . $chooserControl->getData('after_element_html');

        // Restore form prefix (see above).
        $element->getForm()->setHtmlIdPrefix($originalPrefix);

        return $html;
    }

    /**
     * Decorate field row html
     *
     * @param Varien_Data_Form_Element_Abstract $element The element
     * @param string                            $html    Computed HTML for element
     *
     * @return string
     */
    protected function _decorateRowHtml($element, $html)
    {
        return '<tr id="row_' . $element->getHtmlId() . '">' . $html . '</tr>';
    }
}
