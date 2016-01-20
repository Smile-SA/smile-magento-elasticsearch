<?php
/**
 * Custom renderer for "position" field in search term edition
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
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Block_Adminhtml_Catalog_Search_Edit_Tab_Boost_Preview_Renderer_Position
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Renders grid column
     *
     * @param Varien_Object $row The current row
     *
     * @return  string
     */
    public function render(Varien_Object $row)
    {
        $name      = $this->getColumn()->getId() . "[" . $row->getId() . "]";
        $value     = $row->getData($this->getColumn()->getIndex());
        $inlineCss = $this->getColumn()->getInlineCss();

        $html = <<<HTML
        <input type="text" name="{$name}" value="{$value}" class="input-text {$inlineCss}" />
HTML;

        return $html;
    }
}
