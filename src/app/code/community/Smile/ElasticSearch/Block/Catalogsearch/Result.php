<?php
/**
 * Search layer block implementation
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
class Smile_ElasticSearch_Block_Catalogsearch_Result extends Mage_CatalogSearch_Block_Result
{
    /**
     * Retrieve search result count
     *
     * @return string
     */
    public function getResultCount()
    {
        if (!$this->getData('result_count')) {
            $productCollection = $this->_getProductCollection();
            $size = $productCollection->getSize();
            $hasSpellcheck = $productCollection->isSpellchecked();
            $this->_getQuery()->setNumResults($hasSpellcheck ? 0 : $size);

            $this->setResultCount($size);
        }
        return $this->getData('result_count');
    }

    /**
     * Prepare layout
     *
     * @return Mage_CatalogSearch_Block_Result
     */
    protected function _prepareLayout()
    {
        // add Home breadcrumb
        $breadcrumbs = $this->getLayout()->getBlock('breadcrumbs');
        if ($breadcrumbs) {
            $title = $this->__("Search results for: '%s'", $this->helper('catalogsearch')->getQueryText());

            $breadcrumbs->addCrumb(
                'home', array(
                'label' => $this->__('Modyf'),
                'title' => $this->__('Go to Home Page'),
                'link'  => Mage::getBaseUrl()
            )
            )
                ->addCrumb(
                    'search', array(
                    'label' => $title,
                    'title' => $title
                )
                );
        }
        // modify page title
        $title = $this->__("Search results for: '%s'", $this->helper('catalogsearch')->getEscapedQueryText());
        $this->getLayout()->getBlock('head')->setTitle($title);
        return $this;
    }
}
