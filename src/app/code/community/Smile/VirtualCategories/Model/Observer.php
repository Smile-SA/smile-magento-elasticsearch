<?php
/**
 * Virtual categories observer
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
 * @package   Smile_VirtualCategories
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualCategories_Model_Observer
{
    /**
     * Handling rule from the request when saving categories.
     *
     * @param Varien_Event_Observer $observer Event data.
     *
     * @return Smile_VirtualCategories_Model_Observer
     */
    public function prepareCategorySave(Varien_Event_Observer $observer)
    {
        $rule     = $observer->getRequest()->getParam('rule', false);
        $category = $observer->getCategory();
        if ($rule !== false) {
            $ruleInstance = Mage::getModel('smile_virtualcategories/rule')->loadPost($rule);
            $category->setVirtualCategoryRule($ruleInstance);
        }

        return $this;
    }
}

