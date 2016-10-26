<?php

class TNW_Salesforce_Helper_Magento_Order extends TNW_Salesforce_Helper_Magento_Abstract
{
    const SYNC_SUCCESS = 1;

    /**
     * @var null
     */
    protected $_salesforceObject = null;

    /**
     * @see TNW_Salesforce_Model_Sale_Observer::quoteAddressCollectTotalsAfter
     * @return null|stdClass
     */
    public function getSalesforceObject()
    {
        return $this->_salesforceObject;
    }

    /**
     * @param stdClass $object
     * @return mixed
     */
    public function syncFromSalesforce($object = null)
    {
        /**
         * @see TNW_Salesforce_Model_Sale_Observer::quoteAddressCollectTotalsAfter
         */
        $this->_salesforceObject = $object;

        $this->_prepare();

        $_mMagentoId = null;

        $_sSalesforceId = (property_exists($object, "Id") && $object->Id)
            ? $object->Id : null;

        if (!$_sSalesforceId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting order into Magento: ID is missing");
            $this->_addError('Could not upsert Order into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        $_sMagentoIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . "Magento_ID__c";
        $_sMagentoId    = (!empty($object->$_sMagentoIdKey))
            ? $object->$_sMagentoIdKey : null;

        $orderTable = Mage::helper('tnw_salesforce')->getTable('sales_flat_order');
        if (!empty($_sMagentoId)) {
            //Test if user exists
            $sql = "SELECT increment_id  FROM `$orderTable` WHERE increment_id = '$_sMagentoId'";
            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $_mMagentoId = $row['increment_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Order loaded using Magento ID: " . $_mMagentoId);
            }
        }

        if (is_null($_mMagentoId) && !empty($_sSalesforceId)) {
            // Try to find the user by SF Id
            $sql = "SELECT increment_id FROM `$orderTable` WHERE salesforce_id = '$_sSalesforceId'";
            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $_mMagentoId = $row['increment_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Order #" . $_mMagentoId . " Loaded by using Salesforce ID: " . $_sSalesforceId);
            }
        }

        return $this->_updateMagento($object, $_mMagentoId, $_sSalesforceId);
    }

    /**
     * @param $object stdClass
     * @param $_mMagentoId
     * @param $_sSalesforceId
     * @return bool|Mage_Sales_Model_Order
     * @throws Exception
     */
    protected function _updateMagento($object, $_mMagentoId, $_sSalesforceId)
    {
        //Get Address
        $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter('Order')
            ->addFilterTypeSM((bool) $_mMagentoId)
            ->firstSystem();

        if ($_mMagentoId) {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')
                ->load($_mMagentoId, 'increment_id');

            if ($order->getRelationChildId()) {
                $message = Mage::helper('tnw_salesforce')
                    ->__('Trying to edit an order which was previously edited, update your records and try updating the latest version of the edited order.');

                Mage::getSingleton('tnw_salesforce/tool_log')->saveError($message);
                throw new Exception($message);
            }

            if (($this->isItemChange($order, $object) || $this->isTotalChange($order, $object)) && Mage::helper('tnw_salesforce')->isOrderCreateReverseSync()) {
                if (!$order->canEdit()) {
                    $massage = Mage::helper('tnw_salesforce')->__('Order editing is prohibited');
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError($massage);
                    throw new Exception($massage);
                }

                $order->addData(array(
                    'salesforce_id' => $_sSalesforceId,
                    'sf_insync'     => self::SYNC_SUCCESS
                ));

                $this
                    ->_updateMappedEntityFields($object, $order, $mappings)
                    ->_updateMappedEntityItemFields($object, $order)
                    ->_updateNotes($object, $order);

                $this->saveEntities();

                // Create new order
                $newOrder = $this->reorder($order, $object);
                $order = Mage::getSingleton('tnw_salesforce/sale_order_create')->getSession()
                    ->getOrder();

                $this
                    ->_updateMappedEntityFields($object, $newOrder, $mappings)
                    ->_updateMappedEntityItemFields($object, $newOrder)
                    ->_updateStatus($object, $newOrder);

                $this->saveEntities();

                //Sync Orders
                Mage::getSingleton('core/session')->setFromSalesForce(false);

                $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
                Mage::dispatchEvent(sprintf('tnw_salesforce_%s_status_update', $_syncType), array(
                    'order' => $order
                ));

                Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                    'orderIds' => array($newOrder->getId()),
                    'message'  => "SUCCESS: Upserting Order #" . $newOrder->getRealOrderId(),
                    'type'     => 'salesforce'
                ));

                Mage::getSingleton('core/session')->setFromSalesForce(true);
                return $order;
            }
        }
        else {
            if (!Mage::helper('tnw_salesforce')->isOrderCreateReverseSync()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Creating orders with reverse sync disabled');

                return false;
            }

            // Create new order
            $order = $this->create($object, $mappings);
        }

        $order->addData(array(
            'salesforce_id' => $_sSalesforceId,
            'sf_insync'     => self::SYNC_SUCCESS
        ));

        $this
            ->_updateMappedEntityFields($object, $order, $mappings)
            ->_updateMappedEntityItemFields($object, $order, (bool) $_mMagentoId)
            ->_updateNotes($object, $order)
            ->_updateStatus($object, $order);

        $this->saveEntities();
        return $order;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param stdClass $object
     * @return bool
     * @throws Exception
     */
    protected function isItemChange($order, $object)
    {
        $isChange        = false;
        $salesforceIds   = array();
        $hasSalesforceId = array();

        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappingCollection */
        $mappingCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addFieldToFilter('sf_object', array('eq'=>'OrderItem'))
            ->addFieldToFilter('sf_field',  array('in'=> array('Quantity', 'UnitPrice')));

        $hasSfField = $mappingCollection->walk('getSfField');

        /** @var Mage_Sales_Model_Order_Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            if ($item->getProductType() == 'bundle') {
                continue;
            }

            $hasSalesforceId[$item->getId()] = $item->getSalesforceId();
        }

        //Check add element
        foreach ($object->OrderItems->records as $record) {
            $product = $this->_searchProduct($record->PricebookEntry->Product2Id);
            if (is_null($product->getId())) {
                continue;
            }

            if (!$this->isProductValidate($product)) {
                continue;
            }

            $itemId = array_search($record->Id, $hasSalesforceId);
            if (false === $itemId) {
                $isChange = true;
                break;
            }

            /** @var Mage_Sales_Model_Order_Item $item */
            $item = $order->getItemsCollection()->getItemById($itemId);

            $mappingId = array_search('Quantity', $hasSfField);
            if (false === $mappingId) {
                throw new Exception('Quantity mapping not found!');
            }

            $qty = $mappingCollection->getItemById($mappingId)->getModelType()->getValue($item);
            if (floatval($qty) != floatval($record->Quantity)) {
                $isChange = true;
                break;
            }

            $mappingId = array_search('UnitPrice', $hasSfField);
            if (false === $mappingId) {
                throw new Exception('UnitPrice mapping not found!');
            }

            $price = $mappingCollection->getItemById($mappingId)->getModelType()->getValue($item);
            if (!$this->priceCompare($price, $record->UnitPrice)) {
                $isChange = true;
                break;
            }

            $salesforceIds[] = $record->Id;
        }

        //Check remove element
        $result = array_diff($hasSalesforceId, $salesforceIds);
        return $isChange || !empty($result);
    }

    /**
     * @param $magentoPrice
     * @param $salesforcePrice
     * @return bool
     */
    protected function priceCompare($magentoPrice, $salesforcePrice)
    {
        return round(floatval($magentoPrice), 2) == floatval($salesforcePrice);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param stdClass $object
     * @return bool
     */
    protected function isTotalChange($order, $object)
    {
        $feeIds   = $this->getFeeIds();
        foreach ($object->OrderItems->records as $record) {
            $feeType = array_search($record->PricebookEntry->Product2Id, $feeIds);
            if ($feeType === false) {
                continue;
            }

            $isBase = empty($object->CurrencyIsoCode) || (!empty($object->CurrencyIsoCode) && $order->getBaseCurrencyCode() == $object->CurrencyIsoCode);
            switch ($feeType) {
                case 'tax':
                    if (!Mage::helper('tnw_salesforce')->isUpdateTaxTotal()) {
                        $magentoPrice = $record->UnitPrice;
                        break;
                    }

                    $magentoPrice = $isBase ? $order->getBaseTaxAmount() : $order->getTaxAmount();
                    break;

                case 'shipping':
                    if (!Mage::helper('tnw_salesforce')->isUpdateShippingTotal()) {
                        $magentoPrice = $record->UnitPrice;
                        break;
                    }

                    $magentoPrice = $isBase ? $order->getBaseShippingAmount() : $order->getShippingAmount();
                    break;

                case 'discount':
                    if (!Mage::helper('tnw_salesforce')->isUpdateDiscountTotal()) {
                        $magentoPrice = $record->UnitPrice;
                        break;
                    }

                    $magentoPrice = $isBase ? $order->getBaseDiscountAmount() : $order->getDiscountAmount();
                    break;

                default:
                    continue 2;
            }

            if (!$this->priceCompare($magentoPrice, $record->UnitPrice)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function getFeeIds()
    {
        static $feeIds = null;
        if (is_null($feeIds)) {
            $feeIds = array_map(function ($feeData) {
                if (empty($feeData)) {
                    return null;
                }

                $feeData = @unserialize($feeData);
                if (empty($feeData)) {
                    return null;
                }

                return $feeData['Id'];
            }, array(
                'tax'      => Mage::helper('tnw_salesforce')->getTaxProduct(),
                'shipping' => Mage::helper('tnw_salesforce')->getShippingProduct(),
                'discount' => Mage::helper('tnw_salesforce')->getDiscountProduct(),
            ));
        }

        return $feeIds;
    }

    /**
     * @param $object
     * @param $mappings
     * @return Mage_Sales_Model_Order
     * @throws Exception
     */
    protected function create($object, $mappings)
    {
        /** @var TNW_Salesforce_Model_Sale_Order_Create $orderCreate */
        $orderCreate   = Mage::getSingleton('tnw_salesforce/sale_order_create')
            ->setIsValidate(false);

        // Get Customer
        $customer = $this->_searchCustomer($object->BillToContactId);
        if (is_null($customer->getId())) {
            $message = Mage::helper('tnw_salesforce')
                ->__('Trying to create an order, customer not found');

            Mage::getSingleton('tnw_salesforce/tool_log')->saveError($message);
            throw new Exception($message);
        }

        $_websiteId = null;
        $_websiteSfField = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();
        if (property_exists($object, $_websiteSfField)) {
            $_websiteSfId = Mage::helper('tnw_salesforce')
                ->prepareId($object->{$_websiteSfField});

            $_websiteId = array_search($_websiteSfId, $this->_websiteSfIds);
            $_websiteId = ($_websiteId === false) ? null : $_websiteId;
        }

        $storeId = is_null($_websiteId)
            ? Mage::app()->getWebsite(true)->getDefaultGroup()->getDefaultStoreId()
            : Mage::app()->getWebsite($_websiteId)->getDefaultGroup()->getDefaultStoreId();

        /**
         * Identify customer
         */
        $orderCreate->getSession()
            ->setCustomerId((int) $customer->getId())
            ->setStoreId((int) $storeId);

        // Set Currency
        if (!empty($object->CurrencyIsoCode)) {
            $orderCreate->getSession()
                ->setCurrencyId($object->CurrencyIsoCode);
        }

        $orderCreate->setRecollect(true);

        //Get Address
        $address = array(
            'Shipping' => array(),
            'Billing'  => array(),
        );

        /** @var TNW_Salesforce_Model_Mapping $mapping */
        foreach ($mappings as $mapping) {
            $value = property_exists($object, $mapping->getSfField())
                ? $object->{$mapping->getSfField()} : null;

            if (empty($value)) {
                $value = $mapping->getDefaultValue();
            }

            $entityName = $mapping->getLocalFieldType();
            if (!isset($address[$entityName])) {
                continue;
            }

            $field = $mapping->getLocalFieldAttributeCode();
            $address[$entityName][$field] = $value;
        }

        foreach ($address as &$_address) {
            $_countryCode = $this->_getCountryId($_address['country_id']);
            $_regionCode  = null;
            if ($_countryCode) {
                foreach (array('region_id', 'region') as $_regionField) {
                    if (!isset($_address[$_regionField])) {
                        continue;
                    }

                    $_regionCode = $this->_getRegionId($_address[$_regionField], $_countryCode);
                    if (!empty($_regionCode)) {
                        break;
                    }
                }
            }

            $_address['country_id'] = $_countryCode;
            $_address['region_id']  = $_regionCode;
        }

        // Get Product
        $this->addProducts($orderCreate, $object->OrderItems->records);
        if (!$orderCreate->getQuote()->hasItems()) {
            $message = Mage::helper('tnw_salesforce')
                ->__('The quote is empty. Could not move products to create an order.');

            Mage::getSingleton('tnw_salesforce/tool_log')->saveError($message);
            throw new Exception($message);
        }

        $updateItems = array();
        /** @var Mage_Sales_Model_Quote_Item $item */
        foreach ($orderCreate->getQuote()->getItemsCollection() as $itemId => $item) {
            if ($item->isDeleted() || $item->getParentItemId()) {
                continue;
            }

            $value = $item->getOptionByCode('info_buyRequest')->getValue();
            $value = @unserialize($value);
            $salesforceId = $value['salesforce_id'];
            if (isset($sfItems[$salesforceId])) {
                $updateItems[$itemId] = array(
                    'qty'           => $sfItems[$salesforceId]->Quantity,
                    'custom_price'  => $sfItems[$salesforceId]->UnitPrice,
                );
            }
        }

        // Update Item
        $orderCreate->updateQuoteItems($updateItems);

        // Billing Address
        $orderCreate->setBillingAddress(array_merge(array(
            'save_in_address_book'      => 0,
            'firstname'                 => $customer->getData('firstname'),
            'lastname'                  => $customer->getData('lastname'),
            'telephone'                 => '',
            'should_ignore_validation'  => true,
        ), $address['Billing']));

        // Shipping Address
        $orderCreate
            ->setShippingAddress(array_merge(array(
                'save_in_address_book'      => 0,
                'firstname'                 => $customer->getData('firstname'),
                'lastname'                  => $customer->getData('lastname'),
                'telephone'                 => '',
                'should_ignore_validation'  => true,
            ), $address['Shipping']));

        // Shipping Method
        $this->_setShippingMethod($orderCreate);

        // Payment
        $orderCreate->setPaymentData(array(
            'method' => 'tnw_import'
        ));

        try {
            $orderCreate->getSession()->unsetData('order_id');
            $order = $orderCreate->createOrder();

            /** @var Mage_Sales_Model_Order_Item $item */
            foreach ($order->getItemsCollection() as $item) {
                $request = $item->getProductOptionByCode('info_buyRequest');
                $item->setData('salesforce_id', $request['salesforce_id']);
                $this->addEntityToSave(sprintf('Order Item %s', $item->getId()), $item);
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            if (empty($message)) {
                $messages = $orderCreate->getSession()->getMessages(true);
                if ($messages->count() > 0) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError($messages->toString());
                }
            }
            else {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($message);
            }

            throw $e;
        }

        return $order;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param stdClass $object
     * @return Mage_Sales_Model_Order
     * @throws Exception
     */
    protected function reorder($order, $object)
    {
        /** @var TNW_Salesforce_Model_Sale_Order_Create $orderCreate */
        $orderCreate   = Mage::getSingleton('tnw_salesforce/sale_order_create')
            ->setIsValidate(false);

        //FIX: Bundle zero price
        $bundleItems = array();
        /** @var Mage_Sales_Model_Order_Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            //Fix: Delete bundle product
            if ($item->getProductType() == 'bundle') {
                /** @var Mage_Sales_Model_Order_Item $_item */
                foreach ($item->getChildrenItems() as $_item) {
                    $bundleItems[] = $_item->getData('salesforce_id');
                    $order->getItemsCollection()->removeItemByKey($_item->getId());
                }

                $order->getItemsCollection()->removeItemByKey($item->getId());
                continue;
            }

            $options = $item->getProductOptions();
            $options['info_buyRequest']['salesforce_id'] = $item->getData('salesforce_id');
            $item->setProductOptions($options);
        }

        //FIX: Order store
        $orderCreate->getQuote()->setStoreId($order->getStoreId());

        $orderCreate->getQuote()->removeAllItems();
        $orderCreate->initFromOrder($order);

        $sfItems = array();
        /** @var stdClass $record */
        foreach ($object->OrderItems->records as $record) {
            $sfItems[$record->Id] = $record;
        }

        $updateItems = $removeItems = array();

        /** @var Mage_Sales_Model_Quote_Item $item */
        foreach ($orderCreate->getQuote()->getItemsCollection() as $itemId => $item) {
            if ($item->isDeleted() || $item->getParentItemId()) {
                continue;
            }

            $value = $item->getOptionByCode('info_buyRequest')->getValue();
            $value = @unserialize($value);
            $salesforceId = $value['salesforce_id'];
            if (isset($sfItems[$salesforceId])) {
                $updateItems[$itemId] = array(
                    'qty'           => $sfItems[$salesforceId]->Quantity,
                    'custom_price'  => $sfItems[$salesforceId]->UnitPrice,
                    'use_discount'  => true,
                );

                unset($sfItems[$salesforceId]);
            } else {
                $removeItems[] = $itemId;
            }
        }

        // Remove Item
        foreach ($removeItems as $removeItem) {
            $orderCreate->removeQuoteItem($removeItem);
        }

        // Add Item
        foreach ($sfItems as $record) {
            $product = $this->_searchProduct($record->PricebookEntry->Product2Id);
            if (is_null($product->getId())) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice('Product (sku:'.$record->PricebookEntry->Product2->ProductCode.') not found');

                continue;
            }

            if (!$this->isProductValidate($product)) {
                continue;
            }

            $orderCreate->addProduct($product->getId(), array('qty'=>$record->Quantity, 'salesforce_id'=>$record->Id));
        }

        /** @var Mage_Sales_Model_Quote_Item $item */
        foreach ($orderCreate->getQuote()->getItemsCollection() as $itemId => $item) {
            if ($item->isDeleted() || $item->getParentItemId()) {
                continue;
            }

            $value = $item->getOptionByCode('info_buyRequest')->getValue();
            $value = @unserialize($value);
            $salesforceId = $value['salesforce_id'];
            if (isset($sfItems[$salesforceId])) {
                $customPrice = $sfItems[$salesforceId]->UnitPrice;
                if (in_array($salesforceId, $bundleItems) && floatval($customPrice) == 0) {
                    $customPrice = null;
                }

                $updateItems[$itemId] = array(
                    'qty'           => $sfItems[$salesforceId]->Quantity,
                    'custom_price'  => $customPrice,
                    'use_discount'  => true,
                );
            }
        }

        // Update Item
        $orderCreate->updateQuoteItems($updateItems);

        //Unset address cached
        foreach ($orderCreate->getQuote()->getAllAddresses() as $item) {
            $item
                ->setData('should_ignore_validation', true)
                ->unsetData('cached_items_all')
                ->unsetData('cached_items_nominal')
                ->unsetData('cached_items_nonnominal');
        }

        $isVirtual = $orderCreate->getQuote()->isVirtual();
        if (!$isVirtual) {
            $orderCreate->getQuote()->getShippingAddress()->setCollectShippingRates(true);
        }

        if (!$isVirtual && !$orderCreate->getQuote()->getShippingAddress()->requestShippingRates()) {
            $this->_setShippingMethod($orderCreate);
        }
        $orderCreate->getQuote()->setTotalsCollectedFlag(false)->collectTotals();

        try {
            $newOrder = $orderCreate->createOrder();

            /** @var Mage_Sales_Model_Order_Item $item */
            foreach ($newOrder->getItemsCollection() as $item) {
                $request = $item->getProductOptionByCode('info_buyRequest');
                $item->setData('salesforce_id', $request['salesforce_id']);
                $this->addEntityToSave(sprintf('Order Item %s', $item->getId()), $item);
            }

            $orderCreate->getSession()->unsetData('order_id');
        } catch (Exception $e) {
            $message = $e->getMessage();
            if (empty($message)) {
                $messages = $orderCreate->getSession()->getMessages(true);
                if ($messages->count() > 0) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError($messages->toString());
                }
            }
            else {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($message);
            }

            $orderCreate->getSession()->unsetData('order_id');
            throw $e;
        }

        return $newOrder;
    }

    /**
     * @param Mage_Adminhtml_Model_Sales_Order_Create $orderCreate
     */
    protected function _setShippingMethod($orderCreate)
    {
        // Add Shipping Rate
        $shippingRate = new Mage_Shipping_Model_Rate_Result_Method(array(
            'carrier'            => 'tnw',
            'carrier_title'      => 'TNW',
            'method'             => 'import',
            'method_title'       => 'Import',
            'method_description' => 'Custom method for Import',
            'price'              => 0,
        ));

        $rate = Mage::getModel('sales/quote_address_rate')
            ->importShippingRate($shippingRate);

        $orderCreate->getQuote()
            ->getShippingAddress()
            ->addShippingRate($rate);

        // Shipping Method
        $orderCreate->setShippingMethod('tnw_import');
    }

    /**
     * @param Mage_Adminhtml_Model_Sales_Order_Create $orderCreate
     * @param $records
     */
    protected function addProducts($orderCreate, $records)
    {
        foreach ($records as $record) {
            $product = $this->_searchProduct($record->PricebookEntry->Product2Id);
            if (is_null($product->getId())) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice('Product (sku:'.$record->PricebookEntry->Product2->ProductCode.') not found');

                continue;
            }

            if (!$this->isProductValidate($product)) {
                continue;
            }

            $orderCreate->addProduct($product->getId(), array('qty'=>$record->Quantity, 'salesforce_id'=>$record->Id, 'reset_count'=>true));
        }
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return bool
     */
    protected function isProductValidate($product)
    {
        $allowedProductType = array(
            Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL,
        );

        if (!in_array($product->getTypeId(), $allowedProductType)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('Product (sku:'.$product->getSku().') skipping. Product type "'.$product->getTypeId().'"');

            return false;
        }

        $collection = Mage::getResourceModel('catalog/product_option_collection')
            ->addFieldToFilter('product_id', $product->getId())
            ->addRequiredFilter(1);

        if ($collection->getSize() > 1) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('Product (sku:'.$product->getSku().') was skipped. It sas custom option(s).');

            return false;
        }

        return true;
    }

    /**
     * @param $object stdClass
     * @param $order Mage_Sales_Model_Order
     * @param $mappings
     * @return $this
     */
    protected function _updateMappedEntityFields($object, $order, $mappings)
    {
        $entities = array(
            'Order'     => $order,
            'Billing'   => $order->getBillingAddress(),
            'Payment'   => $order->getPayment(),
            'Customer'  => Mage::getModel('customer/customer')->load($order->getCustomerId()),
        );

        if (!$order->getIsVirtual()) {
            $entities['Shipping'] = $order->getShippingAddress();
        }

        //clear log fields before update
        $updateFieldsLog = array();

        $additional = array();

        /** @var TNW_Salesforce_Model_Mapping $mapping */
        foreach ($mappings as $mapping) {
            $newValue = property_exists($object, $mapping->getSfField())
                ? $object->{$mapping->getSfField()} : null;

            if (empty($newValue)) {
                $newValue = $mapping->getDefaultValue();
            }

            $entityName = $mapping->getLocalFieldType();
            $field = $mapping->getLocalFieldAttributeCode();
            if (!isset($entities[$entityName])) {
                continue;
            }

            $entity = $entities[$entityName];
            if ($entity instanceof Mage_Sales_Model_Order_Address) {
                $additional[$entityName][$field] = $newValue;
            }

            if ($entity->getData($field) != $newValue) {
                $entity->setData($field, $newValue);
                $this->addEntityToSave($entityName, $entity);

                //add info about updated field to order comment
                $updateFieldsLog[] = sprintf('%s - from "%s" to "%s"',
                    $mapping->getLocalField(), $entity->getOrigData($field), $newValue);
            }
        }

        foreach ($additional as $entityName => $address) {
            if (!isset($entities[$entityName])) {
                continue;
            }

            $entity = $entities[$entityName];
            if (!$entity instanceof Mage_Sales_Model_Order_Address) {
                continue;
            }

            $_countryCode = $this->_getCountryId($address['country_id']);
            $_regionCode  = null;
            if ($_countryCode) {
                foreach (array('region_id', 'region') as $_regionField) {
                    if (!isset($address[$_regionField])) {
                        continue;
                    }

                    $_regionCode = $this->_getRegionId($address[$_regionField], $_countryCode);
                    if (!empty($_regionCode)) {
                        break;
                    }
                }
            }

            $entity->addData(array(
                'country_id' => $_countryCode,
                'region_id'  => $_regionCode,
            ));

            $this->addEntityToSave($entityName, $entity);
        }

        //add comment about all updated fields
        if (!empty($updateFieldsLog)) {
            $order->addStatusHistoryComment(
                "Fields are updated by salesforce:\n"
                . implode("\n", $updateFieldsLog)
            );
        }

        return $this;
    }

    /**
     * @param $object stdClass
     * @param $order Mage_Sales_Model_Order
     * @param bool $isUpdate
     * @return $this
     */
    protected function _updateMappedEntityItemFields($object, $order, $isUpdate = true)
    {
        /** @var TNW_Salesforce_Model_Mysql4_Mapping_Collection $mappings */
        $mappings = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter('OrderItem')
            ->addFilterTypeSM($isUpdate)
            ->firstSystem();

        /** @var Mage_Sales_Model_Resource_Order_Item_Collection $_orderItemCollection */
        $_orderItemCollection = $order->getItemsCollection();
        $hasSalesforceId      = $_orderItemCollection->walk('getSalesforceId');

        foreach ($object->OrderItems->records as $record) {
            $orderItemId = array_search($record->Id, $hasSalesforceId);
            if (false === $orderItemId) {
                continue;
            }

            /** @var Mage_Sales_Model_Order_Item $entity */
            $entity = $_orderItemCollection->getItemById($orderItemId);

            /** @var $mapping TNW_Salesforce_Model_Mapping */
            foreach ($mappings as $mapping) {
                if ($mapping->getLocalFieldType() != 'Order Item') {
                    continue;
                }

                $newValue = property_exists($record, $mapping->getSfField())
                    ? $record->{$mapping->getSfField()} : null;

                if (empty($newValue)) {
                    $newValue = $mapping->getDefaultValue();
                }

                $entity->setData($mapping->getLocalFieldAttributeCode(), $newValue);
                $this->addEntityToSave(sprintf('Order Item %s', $entity->getId()), $entity);
            }
        }

        return $this;
    }

    /**
     * @param $object stdClass
     * @param $order Mage_Sales_Model_Order
     * @return $this
     */
    protected function _updateStatus($object, $order)
    {
        if (!isset($object->Status) || !$object->Status) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: Order status is not avaialble');
            return $this;
        }

        $matchedStatuses = Mage::getModel('tnw_salesforce/order_status')
            ->getCollection()
            ->addFieldToFilter('sf_order_status', $object->Status);

        if (count($matchedStatuses) === 1) {
            foreach ($matchedStatuses as $_status) {
                if ($order->getStatus() != $_status->getStatus()) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SUCCESS: Order status updated to ' . $_status->getStatus());
                    $oldStatusLabel = $order->getStatusLabel();
                    $order->setStatus($_status->getStatus());
                    $order->addStatusHistoryComment(
                        sprintf("Update from salesforce: status is updated from %s to %s",
                            $oldStatusLabel, $order->getStatusLabel()),
                        $order->getStatus()
                    );
                    $this->addEntityToSave('Order', $order);
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SUCCESS: Order status is in sync');
                }
                break;
            }
        } elseif (count($matchedStatuses) > 1) {
            $log = sprintf('SKIPPING: Order #%s status update.', $order->getIncrementId());
            $log .= ' Mapped Salesforce status matches multiple Magento Order statuses';
            $log .= ' - not sure which one should be selected';
            $order->addStatusHistoryComment($log);
            $this->addEntityToSave('Order', $order);
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: ' . $log);
        } else {
            $message = sprintf('SKIPPING: Order #%s status update.', $order->getIncrementId())
                . ' Mapped Salesforce status does not match any Magento Order status';
            $order->addStatusHistoryComment(
                $message
            );
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: ' . $message);
            $this->addEntityToSave('Order', $order);
        }

        return $this;
    }

    /**
     * @param $object
     * @param $order Mage_Sales_Model_Order
     * @return $this
     */
    protected function _updateNotes($object, $order)
    {
        if (!property_exists($object, 'Notes')) {
            return $this;
        }

        if (empty($object->Notes->records)) {
            return $this;
        }

        $salesforceIds = $order->getStatusHistoryCollection()->walk('getSalesforceId');
        foreach ($object->Notes->records as $record) {
            if (empty($record->Body)) {
                continue;
            }

            $noteId = array_search($record->Id, $salesforceIds);
            if ($noteId === false) {
                $history = Mage::getModel('sales/order_status_history')
                    ->setStatus($order->getStatus())
                    ->setComment($record->Body)
                    ->setSalesforceId($record->Id)
                    ->setEntityName(Mage_Sales_Model_Order::HISTORY_ENTITY_NAME);

                $order->addStatusHistory($history);
            }
            else {
                $order->getStatusHistoryCollection()
                    ->getItemById($noteId)
                    ->setComment($record->Body);
            }
        }

        $this->addEntityToSave('Order', $order);
        return $this;
    }

    /**
     * @param $product2Id
     * @return Mage_Catalog_Model_Product
     */
    protected function _searchProduct($product2Id)
    {
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('salesforce_id', array('like'=>$product2Id));

        return $collection->getFirstItem();
    }

    /**
     * @param $accountId
     * @return Mage_Customer_Model_Customer
     */
    protected function _searchCustomer($accountId)
    {
        $collection = Mage::getResourceModel('customer/customer_collection')
            ->addNameToSelect()
            ->addAttributeToFilter('salesforce_id', array('like'=>$accountId));

        return $collection->getFirstItem();
    }

    /**
     * @param null $_name
     * @return mixed
     */
    protected function _getCountryId($_name  = NULL)
    {
        foreach(Mage::getModel('directory/country_api')->items() as $_country) {
            if (in_array($_name, $_country)) {
                return $_country['country_id'];
            }
        }

        return NULL;
    }

    /**
     * @param null $_name
     * @param null $_countryCode
     * @return mixed
     */
    protected function _getRegionId($_name  = NULL, $_countryCode = NULL)
    {
        foreach(Mage::getModel('directory/region_api')->items($_countryCode) as $_region) {
            if (in_array($_name, $_region)) {
                return $_region['region_id'];
            }
        }

        return NULL;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return $_entity->getIncrementId();
    }
}