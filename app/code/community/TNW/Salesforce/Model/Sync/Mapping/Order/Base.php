<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 10.03.15
 * Time: 22:18
 */
abstract class TNW_Salesforce_Model_Sync_Mapping_Order_Base extends TNW_Salesforce_Model_Sync_Mapping_Abstract_Base
{
    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array(
        'Customer',
        'Billing',
        'Shipping',
        'Custom',
        'Order',
        'Customer Group',
        'Payment',
        'Aitoc'
    );

    /**
     * @var string
     */
    protected $_cachePrefix = 'order';

    /**
     * @var string
     */
    protected $_cacheIdField = 'increment_id';

    /**
     * Apply mapping rules
     */
    protected function _processMapping()
    {
        /** @var  $order Mage_Sales_Model_Order */
        $order = func_get_arg(0);
        $cacheId = $order->getData($this->getCacheIdField());

        if (isset($this->_cache[$this->getCachePrefix() . 'Customers'])
            && is_array($this->_cache[$this->getCachePrefix() . 'Customers'])
            && isset($this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId])
        ) {
            $_customer = $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId];
        } else {
            $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId] = $this->_getCustomer($order);
            $_customer = $this->_cache[$this->getCachePrefix() . 'Customers'][$cacheId];
        }
        $_groupId = ($order->getCustomerGroupId() !== NULL) ? $order->getCustomerGroupId() :  $_customer->getGroupId();

        $modules = Mage::getConfig()->getNode('modules')->children();
        $aitocValues = array('order' => NULL, 'customer' => NULL);
        if (property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
            $aitocValues['customer'] = Mage::getModel('aitcheckoutfields/transport')->loadByCustomerId($_customer->getId());
            $aitocValues['order'] = Mage::getModel('aitcheckoutfields/transport')->loadByOrderId($order->getId());
        }

        $objectMappings = array(
            'Store' => $order->getStore(),
            'Order' => $order,
            'Payment' => $order->getPayment(),
            'Customer' => $_customer,
            'Customer Group' => Mage::getModel('customer/group')->load($_groupId),
            'Billing' => $order->getBillingAddress(),
            'Shipping' => $order->getShippingAddress(),
            'Aitoc' => $aitocValues
        );

        foreach ($this->getMappingCollection() as $_map) {
            /** @var TNW_Salesforce_Model_Mapping  $_map */

            $mappingType = $_map->getLocalFieldType();
            $attributeCode = $_map->getLocalFieldAttributeCode();

            if (!$this->_mappingTypeAllowed($mappingType)) {
                continue;
            }

            $sf_field = $_map->getSfField();

            $value = '';
            $value = $this->_fieldMappingBefore($order, $mappingType, $attributeCode, $value);

            if (!$value) {
                $value = $_map->getValue($objectMappings);
            }

            $value = $this->_fieldMappingAfter($order, $mappingType, $attributeCode, $value);

            if ($value) {
                $this->getObj()->$sf_field = trim($value);
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($this->_type . ' MAPPING: attribute ' . $sf_field . ' does not have a value in Magento, SKIPPING!');
            }
        }
        unset($collection, $_map);
    }

    /**
     * @param $_entity
     * @return false|Mage_Core_Model_Abstract|null
     */
    protected function _getCustomer($_entity)
    {
        return $this->getSync()->getCustomer($_entity);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public static function getOrderDescription(Mage_Sales_Model_Order $order)
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $baseCurrency = Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency();
        $currency = $baseCurrency ? $order->getBaseCurrencyCode() : $order->getOrderCurrencyCode();
        /**
         * use custome currency if Multicurrency enabled
         */
        if ($helper->isMultiCurrency()) {
            $currency = $order->getOrderCurrencyCode();
            $baseCurrency = false;
        }

        ## Put Products into Single field
        $delimiter = '=======================================';
        $lines = array();
        $lines[] = 'Items ordered:';
        $lines[] = $delimiter;
        $lines[] = 'SKU, Qty, Name, Price, Tax, Subtotal, Net Total';
        $lines[] = $delimiter;

        /**
         * @var $item Mage_Sales_Model_Order_Item
         */
        foreach ($order->getAllVisibleItems() as $itemId => $item) {
            $rowTotalInclTax = $baseCurrency ? $item->getBaseRowTotalInclTax() : $item->getRowTotalInclTax();
            $discount = $baseCurrency ? $item->getBaseDiscountAmount() : $item->getDiscountAmount();

            $lines[] = implode(', ', array(
                $item->getSku(),
                $helper->numberFormat($item->getQtyOrdered()),
                $item->getName(),
                $currency . $helper->numberFormat($baseCurrency ? $item->getBasePrice() : $item->getPrice()),
                $currency . $helper->numberFormat($baseCurrency ? $item->getBaseTaxAmount() : $item->getTaxAmount()),
                $currency . $helper->numberFormat($rowTotalInclTax),
                $currency . $helper->numberFormat($rowTotalInclTax - $discount),
            ));
        }
        $lines[] = $delimiter;

        $subtotal = $baseCurrency ? $order->getBaseSubtotal() : $order->getSubtotal();
        $tax = $baseCurrency ? $order->getBaseTaxAmount() : $order->getTaxAmount();
        $shipping = $baseCurrency ? $order->getBaseShippingAmount() : $order->getShippingAmount();
        $discount = $baseCurrency ? $order->getBaseDiscountAmount() : $order->getDiscountAmount();
        $grandTotal = $baseCurrency ? $order->getBaseGrandTotal() : $order->getGrandTotal();
        foreach (array(
                     'Sub Total' => $subtotal,
                     'Tax' => $tax,
                     'Shipping (' . $order->getShippingDescription() . ')' => $shipping,
                     'Discount Amount' => $discount,
                     'Total' => $grandTotal,
                 ) as $label => $totalValue) {
            $lines[] = sprintf('%s: %s%s', $label, $currency, $helper->numberFormat($totalValue));
        }

        return implode("\n", $lines) . "\n";
    }
}
