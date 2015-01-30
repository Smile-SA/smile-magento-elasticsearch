<?php
/**
 * Boolean attributes filter block
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
 * @package   Smile_ElasticSearch
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Block_Catalog_Layer_Filter_Boolean extends Smile_ElasticSearch_Block_Catalog_Layer_Filter_Attribute
{
    /**
     * Defines specific filter model name.
     *
     * @see Smile_ElasticSearch_Model_Catalog_Layer_Filter_Boolean
     */
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'smile_elasticsearch/catalog_layer_filter_boolean';
    }
}
