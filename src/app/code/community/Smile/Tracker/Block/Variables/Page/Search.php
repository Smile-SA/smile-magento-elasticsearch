<?php
/**
 * This block handles variables displayed on search page
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_Tracker
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_Tracker_Block_Variables_Page_Search extends Smile_Tracker_Block_Variables_Page_Abstract
{
    /**
     * Append the user fulltext query to the tracked variables list
     *
     * @return array
     */
    public function getVariables()
    {
        $variables = array(
            'search.query' => Mage::helper('catalogsearch')->getEscapedQueryText()
        );

        if ($layer = Mage::registry('current_layer')) {
            $productCollection = $layer->getProductCollection();
            $variables['search.is_spellchecked'] = (bool) $productCollection->isSpellchecked();
        }
        return $variables;
    }
}