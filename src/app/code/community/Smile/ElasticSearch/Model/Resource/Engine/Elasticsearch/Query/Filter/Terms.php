<?php
/**
 * ElaticSearch query filter terms model.
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
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Filter_Terms
    extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Filter_Abstract
{
    /**
     * Store preprocessed filter options.
     *
     * @var boolean|array
     */
    protected $_parsedFilter = false;

    /**
     * Transform the filter into an ES syntax compliant array.
     *
     * @return array
     */
    public function getFilterQuery()
    {
        if ($this->_parsedFilter === false) {
            foreach ($this->_options as $field => &$values) {
                if (!is_array($values)) {
                    $values = array($values);
                }
            }

            $this->_parsedFilter = true;
        }

        return array('terms' => $this->_options);
    }
}