<?php
/**
 * _______________________________
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
class Smile_SearchOptimizer_RecommendationController extends Mage_Core_Controller_Front_Action
{
    /**
     * This action will switch recommender index to a new one passed in parameters
     *
     * @return void Nothing
     */
    public function aliasAction()
    {
        $indexName = $this->getRequest()->getParam("name", false);

        $alias          = null;
        $deletedIndices = null;

        $response = array("exception" => array());

        if ($indexName) {
            try {
                $response = array_merge($response, $this->_permuteIndex($indexName));

                /** Reindex all data from newly created index */
                $engine       = Mage::helper('catalogsearch')->getEngine();
                $mapping      = $engine->getCurrentIndex()->getMapping('product');
                $dataprovider = $mapping->getDataProvider('popularity');
                $dataprovider->updateAllData();

            } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
                $response['exception'][] = $e->getMessage();
            }
            catch (Exception $e) {
                $response['exception'][] = $e->getMessage();
            }
        }

        $this->getResponse()->setBody(Mage::helper("core")->jsonEncode($response));
    }

    /**
     * Create the new $indexName index and give it the magento recommender alias
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
        $alias   = Mage::helper("smile_searchoptimizer")->getRecommenderIndex();

        $deletedIndices = array();
        $aliasActions = array();
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
            "deleted_indexes" => !is_null($deletedIndices) ? $deletedIndices : ""
        );
    }
}