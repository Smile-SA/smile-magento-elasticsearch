<?php
/**
 * Append the refresh synonyms into the search terms product list.
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
class Smile_ElasticSearch_Block_Adminhtml_Synonym_UpdateButton extends Mage_Adminhtml_Block_Abstract
{

    /**
     * Append the button to the grid container.
     *
     * @return string
     */
    protected function _toHtml()
    {
        $blocks = $this->getLayout()->getAllBlocks();
        foreach ($blocks as $block) {
            if ($block->getType() == 'adminhtml/catalog_search') {
                $buttonParams = array(
                    'label'     => $this->__('Update synomys'),
                    'onclick'   => 'setLocation(\'' . $this->getCreateUrl() .'\')',
                    'class'     => 'reload',
                );
                $block->addButton('updateSynonym', $buttonParams);
            }
        }

        return '';
    }

    /**
     * Return the synonym update controller URL.
     *
     * @return string
     */
    public function getCreateUrl()
    {
        return $this->getUrl('*/synonym/update');
    }

}