<?php

class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_DataProvider_Example
  extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_DataProvider_Abstract
{

    public function getEntitiesData($storeId, $entityIds)
    {
        $result = array();
        foreach ($entityIds as $entityId) {
            if ($entityId % 2) {
                $result[$entityId]['toto'] = 'tito_' .$entityId . '_' . $storeId;
            }
        }
        return $result;
    }
}