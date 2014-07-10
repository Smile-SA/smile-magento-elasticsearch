<?php
/**
 * Optimizer model implementation
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
class Smile_SearchOptimizer_Model_Optimizer extends Mage_Core_Model_Abstract
{

    /**
     * @var string
     */
    const CACHE_TAG      = 'smile_searchoptimizer_optimizer';

    /**
     * @var string
     */
    protected $_cacheTag = 'smile_searchoptimizer_optimizer';

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'smile_searchoptimizer_optimizer';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getOptimizer() in this case
     *
     * @var string
     */
    protected $_eventObject = 'optimizer';

    /**
     * @var Smile_SearchOptimizer_Model_Optimizer_Abstract
     */
    protected $_modelInstance = null;


    /**
     * @var Smile_VirtualCategories_Model_Rule
     */
    protected $_filterRule = null;

    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('smile_searchoptimizer/optimizer');
    }

    /**
     * Serialize config before save.
     *
     * @return Smile_SearchOptimizer_Model_Optimizer
     */
    protected function _beforeSave()
    {

        $config = $this->getConfig() ? $this->getConfig() : array();

        if (is_object($this->_filterRule)) {
            $config['rule_serialized'] = $this->_filterRule->getConditions()->asArray();
            $this->setConfig($config);
        }

        if (is_array($config)) {
            $this->setConfig(serialize($config));
        }

        return $this;
    }

    /**
     * Unserialize config before save.
     *
     * @return Smile_SearchOptimizer_Model_Optimizer
     */
    protected function _afterLoad()
    {

        $config = $this->getData('config');

        if ($config && is_string($config)) {
            $this->setConfig(unserialize($config));
        }

        return $this;
    }

    /**
     * Read the config to retrieve available boost models.
     *
     * @return array
     */
    public function getAvailableModels()
    {
        $availableModels = array();
        $config = Mage::app()->getConfig()->getNode('global/smile_searchoptimizer/optimizer_models')->asArray();
        foreach ($config as $identifier => $modelName) {
            $model = Mage::getModel($modelName);
            if ($model) {
                $availableModels[$modelName] = $model->getName();
            }
        }
        return $availableModels;
    }

    /**
     * Return the model applied for optimizaion.
     *
     * @return Smile_SearchOptimizer_Model_Optimizer_Abstract
     */
    public function getModelInstance()
    {
        if ($this->_modelInstance === null && $this->getModel()) {
            $this->_modelInstance = Mage::getModel($this->getModel(), array('optimizer' => $this));
        }
        return $this->_modelInstance;
    }

    /**
     * Append default value to the form
     *
     * @param Varien_Data_Form $form The form.
     *
     * @return Smile_SearchOptimizer_Model_Optimizer
     */
    public function prepareForm($form)
    {
        $this->getModelInstance()->prepareForm($form, $this);
        foreach ($this->getConfig() as $paramName => $value) {
            $this->setData('config_' . $paramName, $value);
        }
        return $this;
    }

    /**
     * Prepare the filter applied by optimizer (product that will be affected by it).
     *
     * @return Smile_VirtualCategories_Model_Rule
     */
    public function getFilterRule()
    {
        if ($this->_filterRule === null) {

            $this->_filterRule = Mage::getModel('smile_virtualcategories/rule');
            $config = $this->getConfig();

            if (isset($config['rule_serialized'])) {
                $this->_filterRule
                     ->getConditions()
                     ->setConditions(array())
                     ->loadArray($config['rule_serialized']);
            }
        }

        return $this->_filterRule;
    }

    /**
     * Install a new filter rule for the optimizer.
     *
     * @param Smile_VirtualCategories_Model_Rule $rule Rule to be applied.
     *
     * @return Smile_SearchOptimizer_Model_Optimizer
     */
    public function setFilterRule($rule)
    {
        $this->_filterRule = $rule;
        return $this;
    }

    /**
     * Get the query filter for the optimizer current filter rule.
     *
     * @return string
     */
    public function getFilterRuleSearchQuery()
    {
        return $this->getFilterRule()->getConditions()->getSearchQuery();
    }

    /**
     * Returns optimizer configuration.
     * Full config array if key is not specified.
     * Config key value if specified.
     *
     * @param string|null $key Key of the config you want to read.
     *
     * @return mixed
     */
    public function getConfig($key = null)
    {

        $value = $this->getData('config');

        if (is_null($value)) {
            $value = array();
        }

        $value = array_merge($this->getModelInstance()->getDefaultValues(), $value);

        if ($key !== null) {
            $value = isset($value[$key]) ? $value[$key] : null;
        }

        return $value;
    }

    /**
     * Append optimization to the query and return the modified version.
     *
     * @param unknown $query Query to optimize.
     *
     * @return array The modified query
     */
    public function applyOptimizer($query)
    {
        return $this->getModelInstance()->apply($this, $query);
    }
}