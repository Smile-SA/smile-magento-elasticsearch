<?php
/**
 * Overriden Catalog Layer to manage virtual attributes
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
class Smile_VirtualAttributes_Block_Catalogsearch_Layer extends Smile_ElasticSearch_Block_Catalogsearch_Layer
{
    /**
     * Create the block filter for an attribute into the layer.
     *
     * @param Mage_Catalog_Model_Entity_Attribute $attribute Filtered attributes.
     *
     * @return Mage_Catalog_Block_Layer_Filter_Abstract
     */
    protected function _addFilter($attribute)
    {
        if ($this->helper('smile_virtualattributes')->isVirtualAttribute($attribute)) {

            $filterBlockName = $this->helper('smile_virtualattributes')->getFilterBlockName($attribute);
            $filter = $this->getLayout()->createBlock($filterBlockName, $attribute->getAttributeCode() . '_filter')
                ->setLayer($this->getLayer())
                ->setAttributeModel($attribute)
                ->init();

            return $filter;
        }

        return parent::_addFilter($attribute);
    }
}
