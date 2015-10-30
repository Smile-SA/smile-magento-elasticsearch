<?php
/**
 * This block handles variables displayed on order success page.
 * It tracks every needed information about the current order and it's items
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
class Smile_Tracker_Block_Variables_Page_Order extends Smile_Tracker_Block_Variables_Page_Abstract
{
    /**
     * Return order and it's item related variables
     *
     * @return array
     */
    public function getVariables()
    {
        $variables = array();

        $order = $this->getLastOrder();

        if ($order) {
            $variables['order.id'] = $order->getIncrementId();
            $variables['order.subtotal'] = $order->getBaseSubtotalInclTax();
            $variables['order.discount_total'] = $order->getDiscountAmount();
            $variables['order.shipping_total'] = $order->getShippingAmount();
            $variables['order.grand_total'] = $order->getBaseGrandTotal();
            $variables['order.shipping_method'] = $order->getShippingMethod();
            $variables['order.payment_method'] = $order->getPayment()->getMethod();
            $variables['order.salesrules'] = $order->getAppliedRuleIds();

            foreach ($order->getAllItems() as $item) {
                if (!$item->isDummy()) {
                    $itemId = $item->getId();
                    $prefix = "order.items.$itemId";
                    $variables[$prefix . '.sku'] = $item->getSku();
                    $variables[$prefix . '.product_id'] = $item->getProductId();
                    $variables[$prefix . '.qty'] = $item->getQtyOrdered();
                    $variables[$prefix . '.price'] = $item->getBasePrice();
                    $variables[$prefix . '.row_total'] = $item->getRowTotal();
                    $variables[$prefix . '.label'] = $item->getName();
                    $variables[$prefix . '.salesrules'] = $item->getAppliedRuleIds();

                    if ($product = $item->getProduct()) {
                        $categoriesId = $product->getCategoryIds();
                        if (count($categoriesId)) {
                            $variables[$prefix . '.category_ids'] = implode(",", $categoriesId);
                        }
                    }
                }
            }
        }

        return $variables;
    }

    /**
     * Return the order placed by the customer
     *
     * @return Mage_Sales_Model_Order
     */
    public function getLastOrder()
    {
        $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        $order = Mage::getModel('sales/order')->load($orderId);

        if (!$order->getId()) {
            $order = false;
        }

        return $order;
    }
}