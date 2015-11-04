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
     * This action will create a new recommendation index and return ???
     *
     * @return void Nothing
     */
    public function aliasAction()
    {
        $indexName = $this->getRequest()->getParam("name", false);

        $alias          = null;
        $deletedIndices = null;

        if ($indexName) {

            /** @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch $engine */
            $engine = Mage::helper('catalogsearch')->getEngine();

            $indices = $engine->getClient()->indices();
            $alias   = Mage::helper("smile_searchoptimizer")->getRecommenderIndex();

            //$indices->putSettings(array('index' => $indexName, 'body' => array()));

            $deletedIndices = array();
            $aliasActions = array();
            $aliasActions[] = array('add' => array('index' => $indexName, 'alias' => $alias));

            try {
                $allIndices = $indices->getMapping(array('index'=> $alias));
            } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
                $allIndices = array();
            }

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

        }

        $response = array(
            "alias"           => !is_null($alias) ? $alias : "",
            "index_name"      => $indexName,
            "deleted_indexes" => !is_null($deletedIndices) ? $deletedIndices : ""
        );

        $this->getResponse()->setBody(Mage::helper("core")->jsonEncode($response));
    }
}