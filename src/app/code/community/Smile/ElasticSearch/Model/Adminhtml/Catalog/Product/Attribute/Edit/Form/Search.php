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

    protected function _getFieldset(Varien_Data_Form $form)
    {
        $config = array('legend'=>Mage::helper('smile_elasticsearch')->__('Search relevancy'));
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
            'is_snowball_used',
            'select',
            array(
                'name'    => 'is_snowball_used',
                'label'   => Mage::helper('smile_elasticsearch')->__('Use language analysis'),
                'values'  => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray()
            ),
            'is_used_in_autocomplete'
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
            'fuzziness_value',
            'text',
            array(
                'name'  => 'fuzziness_value',
                'label' => Mage::helper('smile_elasticsearch')->__('Fuzziness'),
                'class' => 'validate-number validate-number-range number-range-0-1',
                'note'  => implode(
                    '</br>',
                    array(
                        Mage::helper('smile_elasticsearch')->__('A number between 0 and 1.'),
                        Mage::helper('smile_elasticsearch')->__('See doc at <a href="%s" target="_blank">here</a> for more information', 'http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/common-options.html#_string_fields')
                    )
                )
            ),
            'is_fuzziness_enabled'
        );

        $fieldset->addField(
            'fuzziness_prefix_length',
            'text',
            array(
                'name'  => 'fuzziness_prefix_length',
                'label' => Mage::helper('smile_elasticsearch')->__('Fuzzy prefix range'),
                'class' => 'validate-digits validate-digits-range digits-range-0-9',
                'note'  => implode(
                    '</br>',
                    array(
                        Mage::helper('smile_elasticsearch')->__('A number between 0 and 9.'),
                        Mage::helper('smile_elasticsearch')->__('Min.common prefix between original term and fuzzy matched one.')
                    )
                )
            ),
            'fuzziness_value'
        );


        if ($attribute->getAttributeCode() == 'name') {
            $form->getElement('is_searchable')->setDisabled(1);
            $form->getElement('is_used_in_autocomplete')->setDisabled(1);
            $form->getElement('is_used_in_autocomplete')->setValue(1);
        }

        return $this;
    }
}