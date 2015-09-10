<?php
/**
 * Constant boost score model.
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
class Smile_SearchOptimizer_Model_Optimizer_ConstantScore extends Smile_SearchOptimizer_Model_Optimizer_Abstract
{
    /**
     * Name of the optimizer model.
     *
     * @var string
     */
    protected $_name = 'Constant score';

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
            'config_boost_value',
            'text',
            array(
              'name'      => 'config[boost_value]',
              'label'     => Mage::helper('smile_searchoptimizer')->__('Boost value (%)'),
              'title'     => Mage::helper('smile_searchoptimizer')->__('Boost value (%)'),
              'required'  => true,
              'note'      => Mage::helper('smile_searchoptimizer')->__('Between -99 and 10000'),
              'class'     => 'validate-number-range number-range--99-10000'
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
        $filterRuleSearchQuery = $optimizer->getFilterRuleSearchQuery();

        if ($filterRuleSearchQuery !== false) {
            if (!isset($query['body']['query']['function_score'])) {
                $query['body']['query'] = array(
                    'function_score' => array(
                        'query' => $query['body']['query'],
                        'score_mode' => 'multiply',
                        'boost_mode' => 'multiply',
                    )
                );
            }
            $boostFactor = 1 + ((float) $optimizer->getConfig('boost_value') / 100);
            $query['body']['query']['function_score']['functions'][] = array(
                'boost_factor' => $boostFactor,
                'filter' => array(
                    'fquery' => array(
                        'query' => array('query_string' => array('query' => $filterRuleSearchQuery)),
                        '_cache' => true
                    )
                )
            );
        }

        return $query;
    }
}