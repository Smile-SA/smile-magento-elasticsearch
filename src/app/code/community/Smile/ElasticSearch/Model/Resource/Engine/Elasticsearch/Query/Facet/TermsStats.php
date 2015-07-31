<?php
/**
 * ElaticSearch terms stats facet model.
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
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Facet_TermsStats
    extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Facet_Terms
{
    /**
     * Transform the facet into an ES syntax compliant array.
     *
     * @return array
     */
    protected function _getFacetQuery()
    {
        return array('terms_stats' => $this->_options);
    }
}