<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Abstract
 */
abstract class TNW_Salesforce_Helper_Salesforce_Abstract_Order extends TNW_Salesforce_Helper_Salesforce_Abstract
{


    /**
     * @var array
     */
    protected $_stockItems = array();

    /**
     * @var array
     */
    protected $_alternativeKeys = array();

    /**
     * @param $parentEntityNumber
     * @param Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item $_item
     * @param $_sku
     * @param string $parentEntityType opportunity|order
     * @param string $itemsField {$itemsField}|{$itemsField}
     * @return bool
     */
    protected function _doesCartItemExist($parentEntityNumber, $_item, $_sku, $parentEntityType, $itemsField)
    {

        $_cartItemFound = false;

        /**
         * @var $parentEntityCacheKey string  opportunityLookup|$parentEntityCacheKey
         */
        $parentEntityCacheKey = $parentEntityType . 'Lookup';

        if ($this->_cache[$parentEntityCacheKey] && array_key_exists($parentEntityNumber, $this->_cache[$parentEntityCacheKey]) && $this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$itemsField}) {
            foreach ($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$itemsField}->records as $_cartItem) {
                if (
                    property_exists($_cartItem, 'PricebookEntry')
                    && property_exists($_cartItem->PricebookEntry, 'ProductCode')
                    && $_cartItem->PricebookEntry->ProductCode == trim($_sku)
                    && $_cartItem->Quantity == (float)$_item->getQtyOrdered()
                ) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
                }
            }
        }
        return $_cartItemFound;
    }

    /**
     * @return array
     */
    public function getAlternativeKeys()
    {
        return $this->_alternativeKeys;
    }

    /**
     * @param $_entity
     * @param string $currencyCodeField
     * @return string
     */
    public function getCurrencyCode($_entity, $currencyCodeField = 'order_currency_code')
    {

        $_currencyCode = '';

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $_currencyCode = $_entity->getData($currencyCodeField) . " ";
        }

        return $_currencyCode;
    }


    /**
     * @param $_item
     * @return int
     * Get product Id from the cart
     */
    public function getProductIdFromCart($_item)
    {
        $_options = unserialize($_item->getData('product_options'));
        if (
            $_item->getData('product_type') == 'bundle'
            || array_key_exists('options', $_options)
        ) {
            $id = $_item->getData('product_id');
        } else {
            $id = (int)Mage::getModel('catalog/product')->getIdBySku($_item->getSku());
        }
        return $id;
    }

    /**
     * @comment Prepare order items for Salesforce
     * @throws Exception
     */
    protected function _prepareOrderItem($parentEntityNumber, $parentEntityType)
    {

        $magentoEntity = 'order';
        $magentoEntityPrefix = 'order';

        if ($parentEntityType == 'abandoned') {

            $magentoEntityPrefix = 'abandoned';
            $parentEntityType = 'opportunity';
            $magentoEntity = 'quote';

            /**
             * @var Mage_Sales_Model_Order|Mage_Sales_Model_Quote
             */
            $parentEntity = (Mage::registry($magentoEntityPrefix . '_cached_' . $parentEntityNumber))
                ? Mage::registry($magentoEntityPrefix . '_cached_' . $parentEntityNumber)
                : Mage::getModel('sales/' . $magentoEntity)->load($parentEntityNumber);

            $parentEntity->setDiscountAmount($parentEntity->getSubtotalWithDiscount() - $parentEntity->getSubtotal());
            $parentEntity->setBaseDiscountAmount($parentEntity->getBaseSubtotalWithDiscount() - $parentEntity->getBaseSubtotal());

        } else {
            /**
             * @var Mage_Sales_Model_Order|Mage_Sales_Model_Quote
             */
            $parentEntity = (Mage::registry($magentoEntityPrefix . '_cached_' . $parentEntityNumber))
                ? Mage::registry($magentoEntityPrefix . '_cached_' . $parentEntityNumber)
                : Mage::getModel('sales/' . $magentoEntity)->loadByIncrementId($parentEntityNumber);
        }

        Mage::helper('tnw_salesforce')->log('******** ' . strtoupper($magentoEntity) . ' (' . $parentEntityNumber . ') ********');

        $_currencyCode = $this->getCurrencyCode($parentEntity, $magentoEntity . '_currency_code');

        /**
         * @comment first letter in upper case
         */
        $ucParentEntityType = ucfirst($parentEntityType);
        $manyParentEntityType = $ucParentEntityType;
        $itemsField = $ucParentEntityType;

        if ($parentEntityType == 'opportunity') {
            $manyParentEntityType = 'Opportunities';
            $itemsField .= 'Line';
        } else {
            $manyParentEntityType .= 's';
        }
        $itemsField .= 'Items';

        $_prefix = '';


        /**
         * @comment prepare products for Salesforce order/opportunity
         * @var $_item Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item
         */
        foreach ($parentEntity->getAllVisibleItems() as $_item) {

            $qty = (int)$_item->getQtyOrdered();
            if ($magentoEntity == 'quote') {
                $qty = (int)$_item->getQty();
            }

            if ($qty == 0) {
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addNotice("Product w/ SKU (" . $_item->getSku() . ") for order #" . $parentEntityNumber . " is not synchronized, ordered quantity is zero!");
                }
                Mage::helper('tnw_salesforce')->log("NOTE: Product w/ SKU (" . $_item->getSku() . ") is not synchronized, ordered quantity is zero!");
                continue;
            }
            // Load by product Id only if bundled OR simple with options
            $id = $this->getProductIdFromCart($_item);

            $_storeId = $parentEntity->getStoreId();
            if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                if ($parentEntity->getData('order_currency_code') != $parentEntity->getData('store_currency_code')) {
                    $_storeId = $this->_getStoreIdByCurrency($parentEntity->getData('order_currency_code'));
                }
            }

            /**
             * @var $_productModel Mage_Catalog_Model_Product
             */
            $_productModel = Mage::getModel('catalog/product')->setStoreId($_storeId);
            $_product = $_productModel->load($id);

            $_sku = ($_item->getSku() != $_product->getSku()) ? $_product->getSku() : $_item->getSku();

            $this->_obj = new stdClass();
            if (!$_product->getSalesforcePricebookId()) {
                Mage::helper('tnw_salesforce')->log("ERROR: Product w/ SKU (" . $_item->getSku() . ") is not synchronized, could not add to $parentEntityType!");
                continue;
            }

            //Process mapping
            /**
             * @var $mapping TNW_Salesforce_Model_Sync_Mapping_Abstract_Base
             */
            $mapping = Mage::getSingleton('tnw_salesforce/sync_mapping_' . $magentoEntityPrefix . '_' . $parentEntityType . '_item');

            $mapping->setSync($this)
                ->processMapping($_item, $_product);


            // Check if already exists
            $_cartItemFound = $this->_doesCartItemExist($parentEntityNumber, $_item, $_sku, $parentEntityType, $itemsField);
            if ($_cartItemFound) {
                $this->_obj->Id = $_cartItemFound;
            }

            $parentEntityField = $ucParentEntityType . 'Id';
            $this->_obj->$parentEntityField = $this->_cache['upserted' . $manyParentEntityType][$parentEntityNumber];

            if (!Mage::helper('tnw_salesforce')->useTaxFeeProduct()) {
                $netTotal = $this->getEntityPrice($_item, 'RowTotalInclTax');
            } else {
                $netTotal = $this->getEntityPrice($_item, 'RowTotal');
            }

            if (!Mage::helper('tnw_salesforce')->useDiscountFeeProduct()) {
                $netTotal = ($netTotal - $this->getEntityPrice($_item, 'DiscountAmount'));
                $this->_obj->UnitPrice = $netTotal / $qty;
            } else {
                if ($qty == 0) {
                    $this->_obj->UnitPrice = $netTotal;
                } else {
                    $this->_obj->UnitPrice = $netTotal / $qty;
                }
            }

            /**
             * @comment prepare formatted price
             */
            $this->_obj->UnitPrice = $this->numberFormat($this->_obj->UnitPrice);

            if (!property_exists($this->_obj, "Id")) {
                $this->_obj->PricebookEntryId = $_product->getData('salesforce_pricebook_id');
            }

            $opt = array();
            $options = $_item->getData('product_options');
            $_summary = array();
            if (
                is_array($options)
                && array_key_exists('options', $options)
            ) {
                $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th></tr></thead><tbody>';
                foreach ($options['options'] as $_option) {
                    $opt[] = '<tr><td align="left">' . $_option['label'] . '</td><td align="left">' . $_option['print_value'] . '</td></tr>';
                    $_summary[] = $_option['print_value'];
                }
            }
            if (
                is_array($options)
                && $_item->getData('product_type') == 'bundle'
                && array_key_exists('bundle_options', $options)
            ) {
                $_prefix = '<table><thead><tr><th align="left">Option Name</th><th align="left">Title</th><th>Qty</th><th align="left">Fee<th></tr><tbody>';
                foreach ($options['bundle_options'] as $_option) {
                    $_string = '<td align="left">' . $_option['label'] . '</td>';
                    if (is_array($_option['value'])) {
                        $_tmp = array();
                        foreach ($_option['value'] as $_value) {
                            $_tmp[] = '<td align="left">' . $_value['title'] . '</td><td align="center">' . $_value['qty'] . '</td><td align="left">' . $_currencyCode . ' ' . $this->numberFormat($_value['price']) . '</td>';
                            $_summary[] = $_value['title'];
                        }
                        if (count($_tmp) > 0) {
                            $_string .= join(", ", $_tmp);
                        }
                    }

                    $opt[] = '<tr>' . $_string . '</tr>';
                }
            }
            if (count($opt) > 0) {
                /**
                 * @comment this code works for opportunity only
                 */
                if ($parentEntityType == 'opportunity') {
                    $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Product_Options__c";
                    $this->_obj->$syncParam = $_prefix . join("", $opt) . '</tbody></table>';
                }

                $this->_obj->Description = join(", ", $_summary);
                if (strlen($this->_obj->Description) > 200) {
                    $this->_obj->Description = substr($this->_obj->Description, 0, 200) . '...';
                }
            }

            $this->_obj->Quantity = $qty;

            /**
             * opportunityLineItemsToUpsert
             */
            $this->_cache[lcfirst($itemsField) . 'ToUpsert']['cart_' . $_item->getId()] = $this->_obj;

            /* Dump OrderItem object into the log */
            foreach ($this->_obj as $key => $_objItem) {
                Mage::helper('tnw_salesforce')->log("OrderItem Object: " . $key . " = '" . $_objItem . "'");
            }

            Mage::helper('tnw_salesforce')->log('-----------------');
        }

        // Push Tax Fee Product
        if (Mage::helper('tnw_salesforce')->useTaxFeeProduct() && $parentEntity->getTaxAmount() > 0) {
            if (Mage::helper('tnw_salesforce')->getTaxProduct()) {
                $this->addTaxProduct($parentEntity, $parentEntityNumber, $parentEntityType);
            } else {
                Mage::helper('tnw_salesforce')->log("CRITICAL ERROR: Tax product is not configured!", 1, "sf-errors");
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not add Tax Fee product to the Order!');
                }
            }
        }

        // Push Shipping Fee Product
        if (Mage::helper('tnw_salesforce')->useShippingFeeProduct() && $this->getEntityPrice($parentEntity, 'ShippingAmount') > 0) {
            if (Mage::helper('tnw_salesforce')->getShippingProduct()) {
                $this->addShippingProduct($parentEntity, $parentEntityNumber, $parentEntityType);
            } else {
                Mage::helper('tnw_salesforce')->log("CRITICAL ERROR: Shipping product is not configured!", 1, "sf-errors");
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not add Shipping Fee product to the Order!');
                }
            }
        }

        // Push Discount Fee Product
        if (Mage::helper('tnw_salesforce')->useDiscountFeeProduct() && $this->getEntityPrice($parentEntity, 'DiscountAmount') != 0) {
            if (Mage::helper('tnw_salesforce')->getDiscountProduct()) {
                $this->addDiscountProduct($parentEntity, $parentEntityNumber, $parentEntityType);
            } else {
                Mage::helper('tnw_salesforce')->log("CRITICAL ERROR: Discount product is not configured!", 1, "sf-errors");
                if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addError('WARNING: Could not add Discount Fee product to the Order!');
                }
            }
        }
    }

    /**
     * @comment add vistual product for tax rate
     * @param Mage_Sales_Model_Order $parentEntity
     * @param string $parentEntityNumber
     * @param string $parentEntityType
     */
    protected function addTaxProduct($parentEntity, $parentEntityNumber, $parentEntityType)
    {
        /**
         * @comment first letter in upper case
         */
        $ucParentEntityType = ucfirst($parentEntityType);
        $manyParentEntityType = $ucParentEntityType;
        $itemsField = $ucParentEntityType;

        if ($parentEntityType == 'opportunity') {
            $manyParentEntityType = 'Opportunities';
            $itemsField .= 'Line';
        } else {
            $manyParentEntityType .= 's';
        }
        $itemsField .= 'Items';

        /**
         * @var $parentEntityCacheKey string  opportunityLookup|$parentEntityCacheKey
         */
        $parentEntityCacheKey = $parentEntityType . 'Lookup';

        $_storeId = $parentEntity->getStoreId();
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            if ($parentEntity->getData('order_currency_code') != $parentEntity->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($parentEntity->getData('order_currency_code'));
            }
        }
        $this->_obj = new stdClass();
        $_helper = Mage::helper('tnw_salesforce');
        $_taxProductPricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_TAX_PRODUCT);

        $_cartItemFound = false;
        if (
            is_array($this->_cache[$parentEntityCacheKey]) &&
            array_key_exists($parentEntityNumber, $this->_cache[$parentEntityCacheKey]) &&
            property_exists($this->_cache[$parentEntityCacheKey][$parentEntityNumber], $itemsField)
            && is_object($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$itemsField})
            && property_exists($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$itemsField}, 'records')
        ) {
            foreach ($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$itemsField}->records as $_cartItem) {
                if ($_cartItem->PricebookEntryId == $_taxProductPricebookEntryId) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
                }
            }
        }

        if ($_cartItemFound) {
            $this->_obj->Id = $_cartItemFound;
        }

        $parentEntityField = $ucParentEntityType . 'Id';

        $this->_obj->{$parentEntityField} = $this->_cache['upserted' . $manyParentEntityType][$parentEntityNumber];
        $this->_obj->UnitPrice = $this->numberFormat($this->getEntityPrice($parentEntity, 'TaxAmount'));

        if (!property_exists($this->_obj, "Id")) {
            if ($parentEntity->getData('order_currency_code') != $parentEntity->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($parentEntity->getData('order_currency_code'));
            } else {
                $_storeId = $parentEntity->getStoreId();
            }

            $this->_obj->PricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_TAX_PRODUCT);
        }

        $this->_obj->Description = 'Total Tax';
        $this->_obj->Quantity = 1;

        /* Dump OpportunityLineItem object into the log */
        foreach ($this->_obj as $key => $_item) {
            Mage::helper('tnw_salesforce')->log("OpportunityLineItem Object: " . $key . " = '" . $_item . "'");
        }

        $this->_cache[lcfirst($itemsField) . 'ToUpsert'][] = $this->_obj;
        Mage::helper('tnw_salesforce')->log('-----------------');
    }


    /**
     * @param $parentEntity
     * @param $parentEntityNumber
     * Prepare Shipping fee to Saleforce order
     */
    protected function addShippingProduct($parentEntity, $parentEntityNumber, $parentEntityType)
    {

        /**
         * @comment first letter in upper case
         */
        $ucParentEntityType = ucfirst($parentEntityType);
        $manyParentEntityType = $ucParentEntityType;
        $itemsField = $ucParentEntityType;

        if ($parentEntityType == 'opportunity') {
            $manyParentEntityType = 'Opportunities';
            $itemsField .= 'Line';
        } else {
            $manyParentEntityType .= 's';
        }
        $itemsField .= 'Items';

        /**
         * @var $parentEntityCacheKey string  opportunityLookup|$parentEntityCacheKey
         */
        $parentEntityCacheKey = $parentEntityType . 'Lookup';

        $_storeId = $parentEntity->getStoreId();
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            if ($parentEntity->getData('order_currency_code') != $parentEntity->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($parentEntity->getData('order_currency_code'));
            }
        }
        // Add Shipping Fee to the order
        $this->_obj = new stdClass();

        $_helper = Mage::helper('tnw_salesforce');
        $_shippingProductPricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_SHIPPING_PRODUCT);

        $_cartItemFound = false;
        if (
            is_array($this->_cache[$parentEntityCacheKey]) &&
            array_key_exists($parentEntityNumber, $this->_cache[$parentEntityCacheKey]) &&
            property_exists($this->_cache[$parentEntityCacheKey][$parentEntityNumber], $itemsField)
            && is_object($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$itemsField})
            && property_exists($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$itemsField}, 'records')
        ) {
            foreach ($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$itemsField}->records as $_cartItem) {
                if ($_cartItem->PricebookEntryId == $_shippingProductPricebookEntryId) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
                }
            }
        }
        if ($_cartItemFound) {
            $this->_obj->Id = $_cartItemFound;
        }

        $parentEntityField = $ucParentEntityType . 'Id';

        $this->_obj->{$parentEntityField} = $this->_cache['upserted' . $manyParentEntityType][$parentEntityNumber];
        $this->_obj->UnitPrice = $this->numberFormat(($this->getEntityPrice($parentEntity, 'ShippingAmount')));
        if (!property_exists($this->_obj, "Id")) {
            if ($parentEntity->getData('order_currency_code') != $parentEntity->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($parentEntity->getData('order_currency_code'));
            } else {
                $_storeId = $parentEntity->getStoreId();
            }

            $this->_obj->PricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_SHIPPING_PRODUCT);
        }
        $this->_obj->Description = 'Shipping & Handling';
        $this->_obj->Quantity = 1;

        /* Dump OrderItem object into the log */
        foreach ($this->_obj as $key => $_item) {
            Mage::helper('tnw_salesforce')->log("OrderItem Object: " . $key . " = '" . $_item . "'");
        }
        $this->_cache[lcfirst($itemsField) . 'ToUpsert'][] = $this->_obj;
        Mage::helper('tnw_salesforce')->log('-----------------');
    }

    /**
     * @param $parentEntity
     * @param $parentEntityNumber
     * @param $parentEntityType
     */
    protected function addDiscountProduct($parentEntity, $parentEntityNumber, $parentEntityType)
    {
        /**
         * @comment first letter in upper case
         */
        $ucParentEntityType = ucfirst($parentEntityType);
        $manyParentEntityType = $ucParentEntityType;
        $itemsField = $ucParentEntityType;

        if ($parentEntityType == 'opportunity') {
            $manyParentEntityType = 'Opportunities';
            $itemsField .= 'Line';
        } else {
            $manyParentEntityType .= 's';
        }
        $itemsField .= 'Items';

        /**
         * @var $parentEntityCacheKey string  opportunityLookup|$parentEntityCacheKey
         */
        $parentEntityCacheKey = $parentEntityType . 'Lookup';

        $_storeId = $parentEntity->getStoreId();
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            if ($parentEntity->getData('order_currency_code') != $parentEntity->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($parentEntity->getData('order_currency_code'));
            }
        }
        // Add Shipping Fee to the order
        $this->_obj = new stdClass();

        $_helper = Mage::helper('tnw_salesforce');
        $_discountProductPricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_DISCOUNT_PRODUCT);

        $_cartItemFound = false;
        if (
            is_array($this->_cache[$parentEntityCacheKey]) &&
            array_key_exists($parentEntityNumber, $this->_cache[$parentEntityCacheKey]) &&
            property_exists($this->_cache[$parentEntityCacheKey][$parentEntityNumber], $itemsField)
            && is_object($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$itemsField})
            && property_exists($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$itemsField}, 'records')
        ) {
            foreach ($this->_cache[$parentEntityCacheKey][$parentEntityNumber]->{$itemsField}->records as $_cartItem) {
                if ($_cartItem->PricebookEntryId == $_discountProductPricebookEntryId) {
                    $_cartItemFound = $_cartItem->Id;
                    break;
                }
            }
        }
        if ($_cartItemFound) {
            $this->_obj->Id = $_cartItemFound;
        }

        $parentEntityField = $ucParentEntityType . 'Id';

        $this->_obj->{$parentEntityField} = $this->_cache['upserted' . $manyParentEntityType][$parentEntityNumber];
        $this->_obj->UnitPrice = $this->numberFormat($this->getEntityPrice($parentEntity, 'DiscountAmount'));

        if (!property_exists($this->_obj, "Id")) {
            if ($parentEntity->getData('order_currency_code') != $parentEntity->getData('store_currency_code')) {
                $_storeId = $this->_getStoreIdByCurrency($parentEntity->getData('order_currency_code'));
            } else {
                $_storeId = $parentEntity->getStoreId();
            }

            $this->_obj->PricebookEntryId = Mage::app()->getStore($_storeId)->getConfig($_helper::ORDER_DISCOUNT_PRODUCT);
        }

        $this->_obj->Description = 'Discount';
        $this->_obj->Quantity = 1;

        /* Dump OrderItem object into the log */
        foreach ($this->_obj as $key => $_item) {
            Mage::helper('tnw_salesforce')->log("OpportunityLineItem Object: " . $key . " = '" . $_item . "'");
        }
        $this->_cache[lcfirst($itemsField) . 'ToUpsert'][] = $this->_obj;
        Mage::helper('tnw_salesforce')->log('-----------------');
    }
}