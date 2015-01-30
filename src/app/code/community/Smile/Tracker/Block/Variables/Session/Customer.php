<?php
/**
 * This block handles session variables displayed on all website pages and related to the customer
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
class Smile_Tracker_Block_Variables_Session_Customer extends Smile_Tracker_Block_Variables_Abstract
{
    /**
     * Set the default template for page variable blocks
     *
     * @return void Nothing
     */
    // @codingStandardsIgnoreStart
    public function _construct()
    {
        $this->setTemplate('smile/tracker/variables/session.phtml');
    }
    // @codingStandardsIgnoreEnd


    /**
     * Return the customer related variables to be tracked including :
     *  - customer id
     *  - all customer attributes set into smile_tracker/session/customer_attributes
     *  - all customer address attributes (primary billing address is used)
     *    set into smile_tracker/session/customer_address_attributes
     *
     * @return array
     */
    public function getVariables()
    {
        $variables = array();

        $customer = $this->getCustomer();

        if ($customer && $customer->getId()) {

            $variables['customer.id'] = $customer->getId();

            foreach ($this->getCustomerTrackedAttributes() as $attributeCode) {
                if ($customer->getData($attributeCode)) {
                    $variables['customer.' . $attributeCode] = $customer->getData($attributeCode);
                }
            }

            if ($customer->getPrimaryBillingAddress()) {
                $address = $customer->getPrimaryBillingAddress();
                foreach ($this->getCustomerAddressTrackedAttributes() as $attributeCode) {
                    if ($address->getData($attributeCode)) {
                        $variables['customer.address.' . $attributeCode] = $address->getData($attributeCode);
                    }
                }
            }
        }

        return $variables;
    }

    /**
     * Return the list of customer attributes added to the tracking.
     *
     * @return array
     */
    public function getCustomerTrackedAttributes()
    {
        return array_keys(Mage::getStoreConfig('smile_tracker/session/customer_attributes'));
    }

    /**
     * Return the list of customer address attributes added to the tracking.
     *
     * @return array
     */
    public function getCustomerAddressTrackedAttributes()
    {
        return array_keys(Mage::getStoreConfig('smile_tracker/session/customer_address_attributes'));
    }

    /**
     * Return the current customer from the session
     *
     * @return Mage_Customer_Model_Customer
     */
    public function getCustomer()
    {
        return Mage::getSingleton('customer/session')->getCustomer();
    }
}