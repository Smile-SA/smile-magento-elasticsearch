<?php
/**
 * SOAP Api Model for index
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_SearchOptimizer
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_SearchOptimizer_Model_Popularity_Index_Api extends Mage_Catalog_Model_Api_Resource
{
    /**
     * This method will switch popularity index to a new one passed in parameter
     *  - Associate the new indexName to the magento popularity alias
     *  - Delete previous indexes associated to this alias
     *
     * @param string $indexName The new index name
     *
     * @return array
     */
    public function switchPopularityIndex($indexName)
    {
        $response = array("exception" => array());

        if ($indexName) {
            try {
                $response = array_merge($response, $this->_permuteIndex($indexName));

                /** Invalidate the index */
                $this->_invalidateIndex();
            } catch (Exception $exception) {
                $response['exception'][] = $exception->getMessage();
            }
        }

        $response['status'] = "ok";
        $response['code']   = 200;

        return $response;
    }

    /**
     * Invalidate the Popularity index to schedule its re-calculation
     *
     * @return void
     */
    protected function _invalidateIndex()
    {
        if (Mage::helper("core")->isModuleEnabled("Enterprise_Mview")) {

            $client = Mage::getModel('enterprise_mview/client');
            $client->init(Smile_SearchOptimizer_Model_Indexer_Popularity::DUMMY_TABLE_NAME);

            $metaData = $client->getMetadata();
            $metaData
                ->setViewName(Smile_SearchOptimizer_Model_Indexer_Popularity::METADATA_VIEW_NAME)
                ->setGroupCode(Smile_SearchOptimizer_Model_Indexer_Popularity::METADATA_GROUP_CODE)
                ->setInvalidStatus();

            $metaData->save();

        }
    }

    /**
     * Create the new $indexName index and give it the magento Popularity alias
     * Delete previous indexes associated to this alias
     *
     * @param string $indexName The index name
     *
     * @throws \Elasticsearch\Common\Exceptions\Missing404Exception
     *
     * @return array The response
     */
    protected function _permuteIndex($indexName)
    {
        /** @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch $engine */
        $engine = Mage::helper('catalogsearch')->getEngine();

        $indices = $engine->getClient()->indices();
        $alias   = Mage::helper("smile_searchoptimizer")->getPopularityIndex();

        $deletedIndices = array();
        $aliasActions   = array();
        $aliasActions[] = array('add' => array('index' => $indexName, 'alias' => $alias));

        $allIndices = $indices->getMapping(array('index'=> $alias));

        foreach (array_keys($allIndices) as $index) {
            if ($index != $indexName) {
                $deletedIndices[] = $index;
                $aliasActions[] = array('remove' => array('index' => $index, 'alias' => $alias));
            }
        }

        $indices->updateAliases(array('body' => array('actions' => $aliasActions)));

        foreach ($deletedIndices as $index) {
            $indices->delete(array('index' => $index));
        }

        return array(
            "alias"           => !is_null($alias) ? $alias : "",
            "index_name"      => $indexName,
        );
    }
}