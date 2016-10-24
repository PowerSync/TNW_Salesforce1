<?php

class TNW_Salesforce_Model_Mapping_Type_Order extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Order';

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @return string
     */
    protected function _prepareValue($_entity)
    {
        $attribute = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attribute) {
            case 'cart_all':
                return $this->convertCartAll($_entity);

            case 'number':
                return $this->convertNumber($_entity);

            case 'payment_method':
                return $this->convertPaymentMethod($_entity);

            case 'notes':
                return $this->convertNotes($_entity);

            case 'website':
                return $this->convertWebsite($_entity);

            case 'sf_status':
                return $this->convertSfStatus($_entity);

            case 'sf_name':
                return $this->convertSfName($_entity);

            case 'price_book':
                return $this->convertPriceBook($_entity);

            case 'owner_salesforce_id':
                return $this->convertOwnerSalesforceId($_entity);
        }

        return parent::_prepareValue($_entity);
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @param $value
     * @return mixed|null|string
     */
    protected function _prepareReverseValue($_entity, $value)
    {
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attributeCode) {
            case 'sf_status':
                $this->_mapping->setLocalFieldAttributeCode('status');
                $orderStatus = $this->reverseConvertSfStatus($value);
                return empty($orderStatus) ? $_entity->getStatus() : $orderStatus;
        }

        return parent::_prepareReverseValue($_entity, $value);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function convertCartAll($order)
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

        /** @var TNW_Salesforce_Helper_Salesforce_Order $_helperOrder */
        $_helperOrder = Mage::helper('tnw_salesforce/salesforce_order');

        /** @var $item Mage_Sales_Model_Order_Item */
        foreach ($_helperOrder->getItems($order) as $itemId => $item) {
            if ($_helperOrder->isFeeEntityItem($item)) {
                continue;
            }

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

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return string
     */
    public function convertNumber($_entity)
    {
        return $_entity->getIncrementId();
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return string
     */
    public function convertPaymentMethod($_entity)
    {
        $value = '';
        if ($_entity->getPayment()) {
            $paymentMethods = Mage::helper('payment')->getPaymentMethodList(true);
            $method = $_entity->getPayment()->getMethod();
            if (array_key_exists($method, $paymentMethods)) {
                $value = $paymentMethods[$method];
            } else {
                $value = $method;
            }
        }

        return $value;
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return string
     */
    public function convertNotes($_entity)
    {
        $allNotes = '';
        foreach ($_entity->getStatusHistoryCollection() as $historyItem) {
            $comment = trim(strip_tags($historyItem->getComment()));
            if (!$comment || empty($comment)) {
                continue;
            }

            $allNotes .= Mage::helper('core')->formatTime($historyItem->getCreatedAtDate(), 'medium')
                . " | " . $historyItem->getStatusLabel() . "\n";
            $allNotes .= strip_tags($historyItem->getComment()) . "\n";
            $allNotes .= "-----------------------------------------\n\n";
        }
        return empty($allNotes) ? null : $allNotes;
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return string
     */
    public function convertWebsite($_entity)
    {
        /** @var tnw_salesforce_helper_magento_websites $websiteHelper */
        $websiteHelper = Mage::helper('tnw_salesforce/magento_websites');
        $_website = Mage::app()
            ->getStore($_entity->getStoreId())
            ->getWebsite();

        return $websiteHelper->getWebsiteSfId($_website);
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return string
     */
    public function convertSfStatus($_entity)
    {
        if ('order' == strtolower(Mage::helper('tnw_salesforce')->getOrderObject())) {
            $orderStatus = $this->_getFirstItemStatusMapping($_entity)->getData('sf_order_status');
            return ($orderStatus) ? $orderStatus : Mage::helper('tnw_salesforce/config_sales')->getOrderDraftStatus();
        }
        else {
            $orderStatus = $this->_getFirstItemStatusMapping($_entity)->getData('sf_opportunity_status_code');
            return ($orderStatus)
                ? $orderStatus : 'Committed';
        }
    }

    /**
     * @param string $value
     * @return mixed|null|string
     */
    public function reverseConvertSfStatus($value)
    {
        if ('order' == strtolower(Mage::helper('tnw_salesforce')->getOrderObject())) {
            $matchedStatuses = Mage::getResourceModel('tnw_salesforce/order_status_collection')
                ->addFieldToFilter('sf_order_status', $value);

            return $matchedStatuses->getFirstItem()->getData('status');
        }
        else {
            $matchedStatuses = Mage::getResourceModel('tnw_salesforce/order_status_collection')
                ->addFieldToFilter('sf_opportunity_status_code', $value);

            return $matchedStatuses->getFirstItem()->getData('status');
        }
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return tnw_salesforce_model_order_status
     */
    protected function _getFirstItemStatusMapping($_entity)
    {
        /** @var TNW_Salesforce_Model_Mysql4_Order_Status_Collection $collection */
        $collection = Mage::getResourceModel('tnw_salesforce/order_status_collection')
            ->addStatusToFilter($_entity->getStatus());

        return $collection->getFirstItem();
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return string
     */
    public function convertSfName($_entity)
    {
        if ('order' == strtolower(Mage::helper('tnw_salesforce')->getOrderObject())) {
            return "Magento Order #" . $this->convertNumber($_entity);
        }
        else {
            return "Request #" . $this->convertNumber($_entity);
        }

    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return string
     */
    public function convertPriceBook($_entity)
    {
        $pricebook2Id = null;
        try {
            /** @var tnw_salesforce_helper_data $_helper */
            $_helper = Mage::helper('tnw_salesforce');
            $pricebook2Id = Mage::app()
                ->getStore($_entity->getStoreId())
                ->getConfig($_helper::PRODUCT_PRICEBOOK);
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("INFO: Could not load pricebook based on the order ID. Loading default pricebook based on current store ID.");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            $_standardPricebookId = Mage::helper('tnw_salesforce/salesforce_data')->getStandardPricebookId();
            $pricebook2Id = (Mage::helper('tnw_salesforce')->getDefaultPricebook())
                ? Mage::helper('tnw_salesforce')->getDefaultPricebook() : $_standardPricebookId;
        }

        return $pricebook2Id;
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return string
     */
    public function convertOwnerSalesforceId($_entity)
    {
        $defaultOwner  = Mage::helper('tnw_salesforce')->getDefaultOwner();
        $attributeCode = $this->_mapping->getLocalFieldAttributeCode();
        $currentOwner  = $_entity->getData($attributeCode);

        return $this->_isUserActive($currentOwner) ? $currentOwner : $defaultOwner;
    }
}