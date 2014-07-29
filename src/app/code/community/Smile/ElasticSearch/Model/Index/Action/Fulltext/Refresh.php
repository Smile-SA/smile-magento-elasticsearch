<?php
class Smile_ElasticSearch_Model_Index_Action_Fulltext_Refresh
    implements Enterprise_CatalogSearch_Model_Index_Action_Fulltext_Refresh
{
    /**
     * Run full reindex
     *
     * @return Enterprise_CatalogSearch_Model_Index_Action_Fulltext_Refresh
     * @throws Enterprise_Index_Model_Action_Exception
     */
    public function execute()
    {
        if (Mage::helper('smile_elasticsearch')->isActiveEngine() == false) {
           parent::execute();
        }

        return $this;
    }
}
