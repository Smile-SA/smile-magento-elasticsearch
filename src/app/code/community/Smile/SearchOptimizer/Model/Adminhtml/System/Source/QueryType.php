<?php
/**
 * Query type implementation
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
class Smile_SearchOptimizer_Model_Adminhtml_System_Source_QueryType
{
    /**
     * List of the query types availables.
     *
     * @var array
     */
    protected $_baseOptions = array(
        array('value' => 'product_search_layer', 'label' => 'Catalog product search query'),
        array('value' => 'category_products_layer', 'label' => 'Catalog product category listing'),
    );

    /**
     * Loaded Options.
     *
     * @var array
     */
    protected $_options;

    /**
     * List available query types
     *
     * @param boolean $isMultiselect Includes an empty options if set to false.
     *
     * @return array
     */
    public function toOptionArray($isMultiselect=false)
    {
        if ($this->_options === null) {

            foreach ($this->_baseOptions as $currentOption) {
                $currentOption['label'] = Mage::helper('smile_searchoptimizer')->__($currentOption['label']);
                $this->_options[] = $currentOption;
            }

            $eventData = new Varien_Object(array('options' => $this->_options));
            Mage::dispatchEvent("smile_searchoptimizer_prepare_query_type_list", array("query_types" => $eventData));
            $this->_options = $eventData->getOptions();

        }
        $options = $this->_options;
        if ($isMultiselect == false) {
            array_unshift($options, array('value'=>'', 'label'=> Mage::helper('adminhtml')->__('--Please Select--')));
        }

        return $options;
    }
}
