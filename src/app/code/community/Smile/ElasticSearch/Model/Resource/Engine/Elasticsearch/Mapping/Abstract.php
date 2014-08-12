<?php
/**
 * Abstract class that define a type mapping
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
abstract class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
{
    /**
     * Field type constant
     *
     * @var string
     */
    const FIELD_TYPE_SEARCH = 'search';
    const FIELD_TYPE_FILTER = 'filter';
    const FIELD_TYPE_SORT   = 'sort';

    /**
     * @var string
     */
    protected $_type;

    /**
     * @var array
     */
    protected $_searchFields = null;

    /**
     * Set index type for the current mapping.
     *
     * @param string $type The new type.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
     */
    public function setType($type)
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * Return a list of all searchable field for the current type (by locale code).
     *
     * @param string $localeCode Locale code.
     *
     * @return array.
     */
    abstract public function getSearchFields($localeCode);

    /**
     * Return the ES field name
     *
     * @param string $field      Magento field.
     * @param string $localeCode Locale code we want the field for.
     * @param string $type       How the field will be used : search, facet, sort
     *
     * @return string
     */
    public function getFieldName($field, $localeCode, $type = self::FIELD_TYPE_SEARCH)
    {
        $mapping = $this->getMappingProperties();

        if (in_array($type, array(self::FIELD_TYPE_SEARCH, self::FIELD_TYPE_SORT)) &&
            isset($mapping['properties']['options_' . $field . '_' . $localeCode])
            ) {
            $field = 'options_' . $field . '_' . $localeCode;
        } else {
            if (isset($mapping['properties'][$field . '_' . $localeCode])) {
                $field = $field . '_' . $localeCode;
            }
            if (isset($mapping['properties'][$field]['type'])) {

                if (!in_array($mapping['properties'][$field]['type'], array('string', 'multi_field')) &&
                    $type == self::FIELD_TYPE_SEARCH
                    ) {
                    $field = false;
                }

                if ($field && $mapping['properties'][$field]['type'] == 'multi_field') {
                    $field .= $type == self::FIELD_TYPE_FILTER ? '.untouched' : ($type == self::FIELD_TYPE_SORT ? '.sortable' : '');
                }
            }
        }

        return $field;
    }

    /**
     * Get mapping properties as stored into the index
     *
     * @param string $useCache Indicates if the cache should be used or if the mapping should be rebuilt.
     *
     * @return array
     */
    abstract public function getMappingProperties($useCache = true);

    /**
     * Rebuild the index (full or diff).
     *
     * @param int|null   $storeId Store id the index should be rebuilt for. If null, all store id will be rebuilt.
     * @param array|null $ids     Ids the index should be rebuilt for. If null, processing a fulll reindex
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
     */
    abstract public function rebuildIndex($storeId = null, $ids = null);
}