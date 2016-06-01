<?php
/**
 * Default filter block for virtual flags attributes
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
class Smile_VirtualAttributes_Block_Catalog_Layer_Filter_Attribute_Virtual_Flag
    extends Smile_VirtualAttributes_Block_Catalog_Layer_Filter_Attribute_Virtual
{
    /**
     * Defines specific filter model name.
     *
     * @see Smile_VirtualAttributes_Model_Catalog_Layer_Filter_Attribute_Virtual_Flag
     */
    public function __construct()
    {
        parent::__construct();

        $this->_filterModelName = 'smile_virtualattributes/catalog_layer_filter_attribute_virtual_flag';
    }
}
