<?php
/**
 * Extends catalog product edit form to append search relevancy parameters
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Model_Adminhtml_Catalog_Product_Attribute_Edit_Form_Search
{

    /**
     * Append a new fieldset to the form
     *
     * @param Varien_Data_Form $form The modified form.
     *
     * @return Varien_Data_Form_Element_Fieldset
     */
    protected function _getFieldset(Varien_Data_Form $form)
    {
        $config = array('legend'=>Mage::helper('smile_elasticsearch')->__('Search configuration'));
        $fieldset = $form->addFieldset('search_params_fielset', $config, 'front_fieldset');
        return $fieldset;
    }


    /**
     * Append search params to the form
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute Attribute currently edited
     * @param Varien_Data_Form                          $form      Form the
     *
     * @return Smile_ElasticSearch_Model_Adminhtml_Catalog_Product_Attribute_Edit_Form_Search
     */
    public function addSearchParams($attribute, $form)
    {
        $fieldset = $this->_getFieldset($form);

        $fieldset->addField(
            'search_weight',
            'text',
            array(
                'name'  => 'search_weight',
                'label' => Mage::helper('smile_elasticsearch')->__('Search Weight'),
                'class' => 'validate-number validate-greater-than-zero',
                'value' => '1',
                'default' => 1
            ),
            'is_searchable'
        );

        $fieldset->addField(
            'is_used_in_autocomplete',
            'select',
            array(
                'name'    => 'is_used_in_autocomplete',
                'label'   => Mage::helper('smile_elasticsearch')->__('Used in autocomplete'),
                'values'  => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray()
            ),
            'search_weight'
        );

        $fieldset->addField(
            'is_displayed_in_autocomplete',
            'select',
            array(
                'name'    => 'is_displayed_in_autocomplete',
                'label'   => Mage::helper('smile_elasticsearch')->__('Display in autocomplete'),
                'values'  => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray()
            ),
            'is_used_in_autocomplete'
        );

        $fieldset->addField(
            'is_snowball_used',
            'select',
            array(
                'name'    => 'is_snowball_used',
                'label'   => Mage::helper('smile_elasticsearch')->__('Use language analysis'),
                'values'  => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray()
            ),
            'is_displayed_in_autocomplete'
        );

        $fieldset->addField(
            'is_fuzziness_enabled',
            'select',
            array(
                'name'    => 'is_fuzziness_enabled',
                'label'   => Mage::helper('smile_elasticsearch')->__('Enable fuzziness'),
                'values'  => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray()
            ),
            'is_snowball_used'
        );

        $fieldset->addField(
            'facet_min_coverage_rate',
            'text',
            array(
            'name'  => 'facet_min_coverage_rate',
            'label' => Mage::helper('smile_elasticsearch')->__('Facet coverage rate'),
            'class' => 'validate-digits validate-digits-range digits-range-0-100',
            'value' => '90',
            'note'  => Mage::helper('smile_elasticsearch')->__(
                'Ex: Brand facet will be displayed only if 90% of the product have a brand.'
            )
            ),
            'is_fuzziness_enabled'
        );

        $fieldset->addField(
            'facets_max_size',
            'text',
            array(
                'name'  => 'facets_max_size',
                'label' => Mage::helper('smile_elasticsearch')->__('Facet max. size'),
                'class' => 'validate-digits validate-greater-than-zero',
                'value' => '10',
                'note'  => implode(
                    '</br>',
                    array(
                        Mage::helper('smile_elasticsearch')->__('Max number of values returned by a facet query.'),
                    )
                )
            ),
            'facet_min_coverage_rate'
        );

        $fieldset->addField(
            'facets_sort_order',
            'select',
            array(
                'name'    => 'facets_sort_order',
                'label'   => Mage::helper('smile_elasticsearch')->__('Facet sort order'),
                'values'  => array(
                    array(
                        'value' => Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute::SORT_ORDER_COUNT,
                        'label' => Mage::helper('smile_elasticsearch')->__('Result count'),
                    ),
                    array(
                        'value' => Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute::SORT_ORDER_TERM,
                        'label' => Mage::helper('smile_elasticsearch')->__('Name'),
                    ),
                    array(
                        'value' => Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute::SORT_ORDER_RELEVANCE,
                        'label' => Mage::helper('smile_elasticsearch')->__('Relevance'),
                    ),
                )
            ),
            'facets_max_size'
        );

        if ($attribute->getAttributeCode() == 'name') {
            $form->getElement('is_searchable')->setDisabled(1);
            $form->getElement('is_used_in_autocomplete')->setDisabled(1);
            $form->getElement('is_used_in_autocomplete')->setValue(1);
        }

        return $this;
    }
}
