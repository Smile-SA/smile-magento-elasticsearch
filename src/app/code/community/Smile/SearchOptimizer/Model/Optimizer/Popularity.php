<?php
/**
 * Popularity boost score model.
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
class Smile_SearchOptimizer_Model_Optimizer_Popularity extends Smile_SearchOptimizer_Model_Optimizer_Abstract
{
    /**
     * @var string
     */
    protected $_name = 'Popularity';

    /**
     * @var array
     */
    protected $_defaultValues = array(
        'boost_factor'      => 1,
        'decrease_duration' => 7
    );

    /**
     * Append model configuration to the form.
     *
     * @param Varien_Data_Form                      $form      Form the config should be added to.
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer Current optimizer.
     *
     * @return Smile_SearchOptimizer_Model_Optimizer_Abstract Self reference.
     */
    public function prepareForm($form, $optimizer)
    {

        parent::prepareForm($form, $optimizer);

        $fieldset = $form->getElement('model_config_fieldset');

        $fieldset->addField(
            'config_popularity_type',
            'select',
            array(
                'name'      => 'config[popularity_type]',
                'label'     => Mage::helper('smile_searchoptimizer')->__('Popularity type'),
                'title'     => Mage::helper('smile_searchoptimizer')->__('Popularity type'),
                'required'  => true,
                'options'   => array(
                    'product_order' => Mage::helper('smile_searchoptimizer')->__('Product buyed'),
                    'product_view'  => Mage::helper('smile_searchoptimizer')->__('Product viewed'),
                )
            )
        );

        $fieldset->addField(
            'config_scale_type',
            'select',
            array(
                'name'      => 'config[scale_type]',
                'label'     => Mage::helper('smile_searchoptimizer')->__('Scale function'),
                'title'     => Mage::helper('smile_searchoptimizer')->__('Scale function'),
                'required'  => true,
                'options'   => array(
                    'log' => Mage::helper('smile_searchoptimizer')->__('Logarithmic'),
                    'sqrt'  => Mage::helper('smile_searchoptimizer')->__('Square root'),
                    'none'  => Mage::helper('smile_searchoptimizer')->__('Linear'),
                )
            )
        );

        $fieldset->addField(
            'config_boost_factor',
            'text',
            array(
                'name'      => 'config[boost_factor]',
                'label'     => Mage::helper('smile_searchoptimizer')->__('Scale factor'),
                'title'     => Mage::helper('smile_searchoptimizer')->__('Scale factor'),
                'note'      => Mage::helper('smile_searchoptimizer')->__(
                    'Value the field will be multiplid by the value before applying the scale function'
                ),
                'default'   => 1,
                'required'  => true,
            )
        );
    }

    /**
     * Apply the model to the query.
     *
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer Current optimizer.
     * @param array                                 $query     Query to optimize.
     *
     * @return array The modified query.
     */
    public function apply($optimizer, $query)
    {
        $field = $optimizer->getConfig('popularity_type') == 'product_order' ? '_optimizer_sale_count' : '_optimizer_view_count';
        $scaleType = $optimizer->getConfig('scale_type');
        $valueFactor = (float) $optimizer->getConfig('boost_factor');
        $minValue = ceil(max(1, 1 / $valueFactor));
        $minValueQuery = sprintf('%s:[%d TO *]', $field, $minValue);

        $filterRuleSearchQuery = $optimizer->getFilterRuleSearchQuery();
        if ($filterRuleSearchQuery !== false) {
            $filterRuleSearchQuery = sprintf('(%s) AND %s', $filterRuleSearchQuery, $minValueQuery);
        } else {
            $filterRuleSearchQuery = $minValueQuery;
        }

        if (!isset($query['body']['query']['function_score'])) {
            $query['body']['query'] = array(
                'function_score' => array(
                    'query' => $query['body']['query'],
                    'score_mode' => 'multiply',
                    'boost_mode' => 'multiply',
                )
            );
        }

        $query['body']['query']['function_score']['functions'][] = array(
            'field_value_factor' => array(
                'field'    => $field,
                'factor'   => $valueFactor,
                'modifier' => $scaleType
            ),
            'filter' => array(
                'fquery' => array(
                    'query' => array('query_string' => array('query' => $filterRuleSearchQuery)),
                    '_cache' => true
                )
            )
        );

        return $query;
    }
}
