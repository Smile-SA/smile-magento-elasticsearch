<?php
/**
 * Synonyms update controller
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Adminhtml_SynonymController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Update index settings to refresh the synomyms list.
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    public function updateAction()
    {
        $engine = Mage::helper('catalogsearch')->getEngine();
        $currentIndex = $engine->getCurrentIndex();
        try {
            $currentIndex->updateSynonyms();
            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('smile_elasticsearch')->__('Synonym list have been updated successfully.')
            );
        } catch (Exception $e) {
            Mage::log($e, Zend_Log::ERR, 'search_errors.log');
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('smile_elasticsearch')->__('An error occured while updating synonyms.')
            );
        }

        $this->_redirect('*/catalog_search/index');

        return $this;
    }
}