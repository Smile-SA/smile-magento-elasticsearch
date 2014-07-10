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
                    'log10' => Mage::helper('smile_searchoptimizer')->__('Logarithmic'),
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

        $fieldset->addField(
            'config_decrease_duration',
            'text',
            array(
                'name'      => 'config[decrease_duration]',
                'label'     => Mage::helper('smile_searchoptimizer')->__('Decrease duration (in days)'),
                'title'     => Mage::helper('smile_searchoptimizer')->__('Decrease duration (in days)'),
                'note'      => Mage::helper('smile_searchoptimizer')->__(
                    'Number of day before the boost reaches 50% of it\'s original value'
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
        $rescoreQuery = $this->getRescoreQuery($optimizer);
        $query['body']['rescore'][] = array(
          'window_size' => 1000,
          'query' => array(
            'rescore_query' => $this->getRescoreQuery($optimizer),
            'score_mode'    => 'multiply'
          )
        );

        return $query;
    }

    /**
     * Apply the model to the query.
     *
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer Current optimizer.
     *
     * @return array
     */
    public function getRescoreQuery($optimizer)
    {
        $rescoreChildrenQuery =  array(
          'has_child' => array(
            'score_mode' => 'sum',
            'type'       => 'stats',
            'query'      => array(
              'function_score' => array(
                'filter'    => array('term' => array('event_type' => $optimizer->getConfig('popularity_type'))),
                'functions' => array(
                  $this->getCountScoreFunction($optimizer),
                  $this->getDateScoreFunction($optimizer)
                )
              )
            )
          )
        );

        $rescoreQuery = array(
          'function_score' => array(
            'query'     => $rescoreChildrenQuery,
            'functions' => array()
          )
        );

        if ($optimizer->getConfig('scale_type') != 'none') {
            $rescoreQuery['function_score']['functions'] = array(
                array('script_score' => array('script' => sprintf('%s(_score)', $optimizer->getConfig('scale_type')))),
                array('script_score' => array('script' => '1'))
            );
            $rescoreQuery['function_score']['score_mode'] = 'max';
            $rescoreQuery['function_score']['boost_mode'] = 'replace';
        }

        $filterRuleSearchQuery = $optimizer->getFilterRuleSearchQuery();

        if ($filterRuleSearchQuery !== false) {
            $rescoreQuery['function_score']['query'] = array(
              'filtered' => array(
                'query'  => $rescoreQuery['function_score']['query'],
                'filter' => array('query' => array('query_string' => array('query' => $filterRuleSearchQuery)))
              )
            );
        }

        return $rescoreQuery;
    }

    /**
     * Get the count view / orders function score.
     *
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer Current optimizer.
     *
     * @return array
     */
    public function getCountScoreFunction($optimizer)
    {
        return array(
          'field_value_factor' => array(
            'field'    => 'count',
            'factor'   => (float) $optimizer->getConfig('boost_factor')
          )
        );
    }

    /**
     * Get the date boost factor function score.
     *
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer Current optimizer.
     *
     * @return array
     */
    public function getDateScoreFunction($optimizer)
    {

        return array(
          'linear' => array(
            'date' => array(
              'origin' => Mage::getSingleton('core/date')->gmtDate(Varien_Date::DATE_PHP_FORMAT),
              'scale'  => sprintf('%dd', (int) $optimizer->getConfig('decrease_duration')),
              'decay'  => 0.5
            )
          )
        );
    }
}