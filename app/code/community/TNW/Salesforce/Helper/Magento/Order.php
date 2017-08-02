<?php

class TNW_Salesforce_Helper_Magento_Order extends TNW_Salesforce_Helper_Magento_Order_Base
{
    const SYNC_SUCCESS = 1;

    /**
     * @var string
     */
    protected $_mappingEntityName = 'Order';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'OrderItem';

    /**
     * @comment salesforce entity alias
     * @var string
     */
    protected $_salesforceEntityName = 'order';

    /**
     * @param $object stdClass
     * @param $_mMagentoId
     * @param $_sSalesforceId
     * @return bool|Mage_Sales_Model_Order
     * @throws Exception
     */
    protected function _updateMagento($object, $_mMagentoId, $_sSalesforceId)
    {

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
                    ->_updateMappedEntityFields($object, $order)
                    ->_updateMappedEntityItemFields($object, $order)
                    ->_updateNotes($object, $order);

                $this->saveEntities();

                /** @var TNW_Salesforce_Model_Sale_Order_Create $orderCreate */
                $orderCreate   = Mage::getModel('tnw_salesforce/sale_order_create')
                    ->setIsValidate(false);

                // Create new order
                $newOrder = $this->reorder($orderCreate, $order, $object);
                $order = $orderCreate->getSession()->getOrder();

                $this
                    ->_updateMappedEntityFields($object, $newOrder)
                    ->_updateMappedEntityItemFields($object, $newOrder);

                $this->saveEntities();

                //Sync Orders
                Mage::getSingleton('core/session')->setFromSalesForce(false);
                Mage::dispatchEvent('tnw_salesforce_sync_order_status_for_website', array(
                    'order' => $order
                ));

                Mage::dispatchEvent('tnw_salesforce_sync_order_for_website', array(
                    'entityIds' => array($newOrder->getId()),
                    'syncType' => 'realtime'
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

            /** @var TNW_Salesforce_Model_Sale_Order_Create $orderCreate */
            $orderCreate   = Mage::getModel('tnw_salesforce/sale_order_create')
                ->setIsValidate(false);

            // Create new order
            $order = $this->create($orderCreate, $object);
        }

        $order->addData(array(
            'salesforce_id' => $_sSalesforceId,
            'sf_insync'     => self::SYNC_SUCCESS
        ));

        $this
            ->_updateMappedEntityFields($object, $order)
            ->_updateMappedEntityItemFields($object, $order, (bool) $_mMagentoId)
            ->_updateNotes($object, $order);

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
            ->addFieldToFilter('sf_field',  array('in'=> array('Quantity', 'UnitPrice')))
            ->addFieldToFilter('sf_magento_enable', 1)
            ->firstSystem()
        ;

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
        foreach ($this->getFeeIds() as $feeType => $productId) {
            if (empty($productId)) {
                continue;
            }

            if (!Mage::helper('tnw_salesforce')->useFeeByType($feeType)) {
                continue;
            }

            if (!Mage::helper('tnw_salesforce')->isUpdateTotalByFeeType($feeType)) {
                continue;
            }

            $magentoPrice = empty($object->CurrencyIsoCode) || (!empty($object->CurrencyIsoCode) && $order->getBaseCurrencyCode() == $object->CurrencyIsoCode)
                ? (float)$order->getData("base_{$feeType}_amount")
                : (float)$order->getData("{$feeType}_amount");

            if (empty($magentoPrice)) {
                continue;
            }

            $orderItem = $this->searchOrderItemByProductId($object->OrderItems->records, $productId);
            if (null === $orderItem) {
                return true;
            }

            if (!$this->priceCompare($magentoPrice, $orderItem->UnitPrice)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $orderItems
     * @param string $productId
     * @return stdClass|null
     */
    public function searchOrderItemByProductId(array $orderItems, $productId)
    {
        foreach ($orderItems as $orderItem) {
            if (strcasecmp($orderItem->PricebookEntry->Product2Id, $productId) !== 0) {
                continue;
            }

            return $orderItem;
        }

        return null;
    }

    /**
     * @return array
     */
    public function getFeeIds()
    {
        return array_filter(array_map(function ($feeData) {
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
        )));
    }

    /**
     * @param $orderCreate TNW_Salesforce_Model_Sale_Order_Create
     * @param $object
     * @return Mage_Sales_Model_Order
     * @throws Exception
     */
    protected function create($orderCreate, $object)
    {
        $_websiteId = false;
        $_websiteSfField = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();
        if (property_exists($object, $_websiteSfField)) {
            $_websiteSfId = Mage::helper('tnw_salesforce')
                ->prepareId($object->{$_websiteSfField});

            $_websiteId = array_search($_websiteSfId, $this->_websiteSfIds);
        }

        $_websiteId = ($_websiteId === false) ? Mage::app()->getWebsite(true)->getId() : $_websiteId;
        $storeId    = Mage::app()->getWebsite($_websiteId)
            ->getDefaultGroup()->getDefaultStoreId();

        $orderCreate->getSession()
            ->setStoreId((int)$storeId);

        // Get Customer
        $customer = $this->_searchCustomer($object->AccountId, $_websiteId);
        if (null !== $customer->getId()) {
            $orderCreate->getSession()
                ->setCustomer($customer)
                ->setCustomerId((int) $customer->getId());
        } else {
            $orderCreate->getQuote()
                ->setStoreId((int)$storeId)
                ->setCustomerId(null)
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)
                ->setIsActive(false)
                ->save();
        }

        $orderCreate->getQuote()
            ->setData('_salesforce_object', $object);

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

        $mappings = $this->getMappingByType($orderCreate, 'Order');

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

        $sfItems = array();
        /** @var stdClass $record */
        foreach ($object->OrderItems->records as $record) {
            $sfItems[$record->Id] = $record;
        }

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
     * @param $orderCreate TNW_Salesforce_Model_Sale_Order_Create
     * @param Mage_Sales_Model_Order $order
     * @param stdClass $object
     * @return Mage_Sales_Model_Order
     * @throws Exception
     */
    protected function reorder($orderCreate, $order, $object)
    {
        $orderCreate->getSession()
            ->setCustomerId($order->getCustomerId())
            ->setStoreId($order->getStoreId());

        $orderCreate->getQuote()
            ->setData('_salesforce_object', $object);

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

        //FIX: $orderCreate->initRuleData()
        Mage::unregister('rule_data');
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
     * @param $contactId
     * @param $websiteId
     * @return Mage_Customer_Model_Customer
     */
    protected function _searchCustomer($contactId, $websiteId)
    {
        $collection = Mage::getResourceModel('customer/customer_collection')
            ->addNameToSelect()
            ->addAttributeToFilter('salesforce_account_id', array('like'=>$contactId));

        if (Mage::getSingleton('customer/config_share')->isWebsiteScope()) {
            $collection->addAttributeToFilter('website_id', array('eq'=>$websiteId));
        }

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
}