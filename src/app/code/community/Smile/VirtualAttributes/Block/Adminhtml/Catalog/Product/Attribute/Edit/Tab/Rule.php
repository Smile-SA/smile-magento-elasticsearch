<?php
/**
 * Virtual attributes rule Tab
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
 * @package   Smile_VirtualAttributes
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2016 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualAttributes_Block_Adminhtml_Catalog_Product_Attribute_Edit_Tab_Rule
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * @var null Attribute model
     */
    protected $_attribute = null;

    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__("Rule conditions");
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__("Rule conditions");
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        return Mage::helper('smile_virtualattributes')->isVirtualAttribute($this->getAttributeObject());
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return !(Mage::helper('smile_virtualattributes')->isVirtualAttribute($this->getAttributeObject()));
    }

    /**
     * Prepare the form.
     *
     * @return Smile_VirtualAttributes_Block_Adminhtml_Catalog_Product_Attribute_Edit_Tab_Rule
     */
    protected function _prepareForm()
    {
        $model = $this->getAttributeObject();
        $form = new Varien_Data_Form();
        $form->setHtmlIdPrefix('rule_');
        $this->_prepareRule($form, $model);
        $form->addValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Get currently edited attribute.
     *
     * @return Smile_VirtualAttributes_Block_Adminhtml_Catalog_Product_Attribute_Edit_Tab_Rule
     */
    public function getAttributeObject()
    {
        if (null === $this->_attribute) {
            return Mage::registry('entity_attribute');
        }
        return $this->_attribute;
    }

    /**
     * Render rule form for attribute
     *
     * @param Varien_Data_Form         $form  The Form
     * @param Mage_Eav_Model_Attribute $model The attribute
     *
     * @return $this
     */
    private function _prepareRule($form, $model)
    {
        $url = Mage::helper('adminhtml')->getUrl('*/promo_catalog/newConditionHtml', array('form' => 'optimizer_virtual_fieldset'));

        $options = $model->getSource()->getAllOptions();

        $renderer = Mage::getBlockSingleton('adminhtml/widget_form_renderer_fieldset')
            ->setTemplate('smile/virtualcategories/category/rule_fieldset.phtml')
            ->setNewChildUrl($url);

        $contributionNotice = $this->_getAttributeNotice($model);
        if ($contributionNotice) {
            $noticeFieldset = $form->addFieldset(
                'notice_fieldset',
                ['legend' => $this->__('Conditions application'), 'class' => 'fieldset-wide']
            );
            $noticeFieldset->addField('virtual_notice', 'note', ['text' => $contributionNotice]);
        }

        $fieldset = $form->addFieldset(
            'virtual_fieldset',
            array('legend' => $this->__('Apply to products'), 'class' => 'fieldset-wide')
        )->setRenderer($renderer);

        $fieldset->addField('virtual_hide_options', 'hidden', array())->setAfterElementHtml($this->_getAdditionalJavascript());

        foreach ($options as $option) {

            if ((!isset($option['value']) || ($option['value'] == null)) && (count($options) > 1)) {
                // @TODO Remove this to enable having several options.
                continue;
            }

            $optionId = isset($option['value']) ? $option['value'] : null;

            $fieldset->addField(
                "option_id",
                'hidden',
                array(
                    'name'  => "option_id",
                    'value' => $optionId
                )
            );

            $field = $fieldset->addField(
                "conditions[$optionId]",
                'text',
                array(
                    'name' => "conditions[$optionId]",
                    'label' => Mage::helper('catalogrule')->__('Conditions'),
                    'title' => Mage::helper('catalogrule')->__('Conditions'),
                    'required' => true,
                )
            );

            $optionRule = isset($option['rule']) ? $option['rule'] : null;
            $rule = Mage::helper('smile_virtualattributes')->getFilterRule($model, $optionId, $optionRule);

            $rule->setForm($form);
            $rule->getConditions()->setJsFormObject('attribute_virtual_fieldset');
            $field->setRule($rule)->setRenderer(Mage::getBlockSingleton('rule/conditions'));
        }

        return $this;

    }

    /**
     * Append additional javascript to hide "manage options" panel for virtual attributes.
     *
     * @return string
     */
    protected function _getAdditionalJavascript()
    {
        $javascript = "";

        if (Mage::helper('smile_virtualattributes')->isVirtualAttribute($this->getAttributeObject())) {
            $javascript = <<<JAVASCRIPT
                <script type="text/javascript">
                    document.observe("dom:loaded", function() {
                        if($('matage-options-panel')){
                            $('matage-options-panel').remove();
                        }
                    });
                </script>
JAVASCRIPT;
        }

        return $javascript;
    }

    /**
     * Append text note for a given attribute.
     *
     * @return string
     */
    protected function _getAttributeNotice($attributeModel)
    {
        return Mage::helper("smile_virtualattributes")->getAttributeNotice($attributeModel);
    }
}
