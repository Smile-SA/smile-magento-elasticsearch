<?php

class Smile_ElasticSearch_Model_Index_Action_Fulltext_Refresh_Row
    extends Enterprise_CatalogSearch_Model_Index_Action_Fulltext_Refresh_Row
{

    /**
     * Refresh rows by ids from changelog
     *
     * @return Enterprise_CatalogSearch_Model_Index_Action_Fulltext_Refresh_Changelog
     *
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