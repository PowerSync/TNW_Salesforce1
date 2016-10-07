<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Order_Invoice
 *
 * @method Mage_Sales_Model_Order_Invoice _loadEntityByCache($_entityId, $_entityNumber)
 */
class TNW_Salesforce_Helper_Salesforce_Order_Invoice extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    /**
     * @comment magento entity alias "convert from"
     * @var string
     */
    protected $_magentoEntityName = 'invoice';

    /**
     * @comment salesforce entity alias "convert to"
     * @var string
     */
    protected $_salesforceEntityName = 'orderInvoice';

    /**
     * @var string
     */
    protected $_mappingEntityName = 'OrderInvoice';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'OrderInvoiceItem';

    /**
     * @comment magento entity model alias
     * @var array
     */
    protected $_magentoEntityModel = 'sales/order_invoice';

    /**
     * @var array
     */
    protected $_availableFees = array(
        'tax',
        'shipping',
        'discount'
    );

    /**
     * @var int
     */
    protected $_guestCount = 0;

    /**
     * @var array
     */
    protected $_emails = array();

    /**
     * @var array
     */
    protected $_websites = array();

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return $_entity->getIncrementId();
    }

    /**
     * @param array $_ids
     */
    protected function _massAddBefore($_ids)
    {
        $this->_guestCount = 0;
        $this->_emails = $this->_websites = array();
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @return bool
     */
    protected function _checkMassAddEntity($_entity)
    {
        // Parent in Salesforce
        $_order = $_entity->getOrder();
        if (!$_order->getSalesforceId() || !$_order->getData('sf_insync')) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')
                    ->addError('WARNING: Sync for invoice #' . $_entity->getIncrementId() . ', order #' . $_order->getRealOrderId() . ' needs to be synchronized first!');
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveNotice('SKIPPING: Sync for invoice #' . $_entity->getIncrementId() . ', order #' . $_order->getRealOrderId() . ' needs to be synchronized first!');
            return false;
        }

        $_recordNumber = $this->_getEntityNumber($_entity);

        // Get Magento customer object
        $customer = $this->_generateCustomerByOrder($_order);

        // Associate order Number with a customer ID
        $_customerId = ($customer->getId())
            ? $customer->getId() : sprintf('guest_%d', $this->_guestCount++);

        $customer->setId($_customerId);

        // Associate order Number with a customer Email
        $email = strtolower($customer->getEmail());
        if (empty($email)) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                $message = sprintf('SKIPPED: Sync for %s #%s failed, %s is missing an email address!',
                    $this->_magentoEntityName, $_recordNumber, $this->_magentoEntityName);

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveNotice($message);
                Mage::getSingleton('adminhtml/session')
                    ->addNotice($message);
            }

            return false;
        }

        $this->_cache[sprintf('%sToCustomerId', $this->_magentoEntityName)][$_recordNumber] = $_customerId;
        $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_recordNumber] = $customer;
        $this->_cache[sprintf('%sToEmail', $this->_magentoEntityName)][$_recordNumber] = $email;

        // Check if customer from this group is allowed to be synchronized
        $_customerGroup = $_order->getData('customer_group_id');
        if ($_customerGroup === NULL) {
            $_customerGroup = $customer->getGroupId();
        }

        if ($_customerGroup === NULL && !$this->isFromCLI()) {
            $_customerGroup = Mage::getSingleton('customer/session')->getCustomerGroupId();
        }

        if (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($_customerGroup)) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("SKIPPING: Sync for customer group #" . $_customerGroup . " is disabled!");
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addNotice('SKIPPED: Sync for invoice #' . $_recordNumber . ', sync for customer group #' . $_customerGroup . ' is disabled!');
            }

            return false;
        }

        $_websiteId = ($customer->getData('website_id'))
            ? $customer->getData('website_id')
            : Mage::app()->getStore($_entity->getData('store_id'))->getWebsiteId();

        $this->_emails[$_customerId]   = $email;
        $this->_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];

        return true;
    }

    /**
     * Sync customer w/ SF before creating the order
     *
     * @param $order Mage_Core_Model_Abstract|Mage_Sales_Model_Order|Mage_Sales_Model_Quote
     * @return Mage_Customer_Model_Customer
     * @deprecated
     */
    protected function _getCustomer($order)
    {
        return $this->_generateCustomerByOrder($order);
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        // Salesforce lookup, find all contacts/accounts by email address
        $this->_cache['contactsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')
            ->lookup($this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)]);

        $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_account')
            ->lookup($this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)]);

        $this->_massAddAfterInvoice();

        /**
         * define Salesforce data for order customers
         */
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $key => $number) {
            $entity         = $this->_loadEntityByCache($key, $number);
            /** @var Mage_Customer_Model_Customer $customer */
            $customer       = $this->_getObjectByEntityType($entity, 'Customer');
            $customerEmail  = strtolower($customer->getEmail());

            if (!empty($this->_cache['accountsLookup'][0][$customerEmail])) {
                $_websiteId = $this->_websites[$this->_cache[sprintf('%sToCustomerId', $this->_magentoEntityName)][$number]];

                $customer->setData('salesforce_account_id', $this->_cache['accountsLookup'][0][$customerEmail]->Id);

                // Overwrite Contact Id for Person Account
                if (property_exists($this->_cache['accountsLookup'][0][$customerEmail], 'PersonContactId')) {
                    $customer->setData('salesforce_id', $this->_cache['accountsLookup'][0][$customerEmail]->PersonContactId);
                }

                // Overwrite from Contact Lookup if value exists there
                if (isset($this->_cache['contactsLookup'][$_websiteId][$customerEmail])) {
                    $customer->setData('salesforce_id', $this->_cache['contactsLookup'][$_websiteId][$customerEmail]->Id);
                }
            }
            else {
                /**
                 * No customers for this order in salesforce - error
                 */
                // Something is wrong, could not create / find Magento customer in SalesForce
                $this->logError('CRITICAL ERROR: Contact or Lead for Magento customer (' . $customerEmail . ') could not be created / found!');
                $this->_skippedEntity[$key] = $key;

                continue;
            }
        }

        foreach ($this->_skippedEntity as $_idToRemove) {
            unset($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING][$_idToRemove]);
        }
    }

    /**
     *
     */
    protected function _massAddAfterInvoice()
    {
        // Salesforce lookup, find all orders by Magento order number
        $this->_cache[sprintf('%sLookup', $this->_salesforceEntityName)] = Mage::helper('tnw_salesforce/salesforce_data_invoice')
            ->lookup($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);

        $orders = array();
        foreach ($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING] as $key=>$number) {
            $invoice = $this->_loadEntityByCache($key, $number);
            $orders[] = $invoice->getOrder()->getRealOrderId();
        }

        $this->_cache['orderLookup'] = Mage::helper('tnw_salesforce/salesforce_data_order')
            ->lookup($orders);
    }

    /**
     * @param $_entity
     * @param $key
     */
    protected function _prepareEntityObjCustom($_entity, $key)
    {
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $this->getCurrencyCode($_entity);
        }

        // Link to Order
        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Order__c'}
            = $_entity->getOrder()->getData('salesforce_id');

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'disableMagentoSync__c'}
            = true;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @param $type string
     * @return mixed
     */
    protected function _getObjectByEntityType($_entity, $type)
    {
        switch($type)
        {
            case 'Invoice':
                $_object = $_entity;
                break;

            case 'Order':
                $_object = $_entity->getOrder();
                break;

            case 'Payment':
                $_order = $this->_getObjectByEntityType($_entity, 'Order');
                $_object = $_order->getPayment();
                break;

            case 'Customer':
                $_entityNumber = $this->_getEntityNumber($_entity);
                $_object = $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_entityNumber];
                break;

            case 'Customer Group':
                $_customer = $this->_getObjectByEntityType($_entity, 'Customer');
                $_order    = $this->_getObjectByEntityType($_entity, 'Order');
                $_groupId  = ($_order->getCustomerGroupId() !== NULL)
                    ? $_order->getCustomerGroupId() : $_customer->getGroupId();

                $_object = Mage::getModel('customer/group')->load($_groupId);
                break;

            case 'Billing':
                $_object = $_entity->getBillingAddress();
                break;

            case 'Shipping':
                $_object = $_entity->getShippingAddress();
                break;

            case 'Custom':
                $_object = $_entity->getStore();
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
    }

    /**
     * @param $_entityItem
     * @return bool|void
     */
    protected function _getEntityItemSalesforceId($_entityItem)
    {
        $_entity = $this->getEntityByItem($_entityItem);
        return $this->_doesCartItemExist($_entity, $_entityItem);
    }

    /**
     * @param $_entityItem Mage_Sales_Model_Order_Invoice_Item
     * @param $_type
     * @return mixed
     * @throws Exception
     */
    protected function _getObjectByEntityItemType($_entityItem, $_type)
    {
        switch($_type)
        {
            case 'Invoice':
                $_object = $this->getEntityByItem($_entityItem);
                break;

            case 'Billing Item':
                $_object = $_entityItem;
                break;

            case 'Product':
                $_productId = $this->getProductIdFromCart($_entityItem->getOrderItem());
                $storeId    = $this->_getObjectByEntityItemType($_entityItem, 'Custom')->getId();

                /** @var Mage_Catalog_Model_Product $_product */
                $_product   = Mage::getModel('catalog/product')
                    ->setStoreId($storeId);

                if (!empty($_productId)) {
                    $_object = $_product->load($_productId);
                    break;
                }
                else {
                    $_object = $_product->addData(array(
                        'name'           => $_entityItem->getData('Name'),
                        'sku'            => $_entityItem->getData('ProductCode'),
                        'salesforce_id'  => $_entityItem->getData('Id'),
                    ));
                    break;
                }

            case 'Product Inventory':
                $product = $this->_getObjectByEntityItemType($_entityItem, 'Product');
                $_object = Mage::getModel('cataloginventory/stock_item')
                    ->loadByProduct($product);
                break;

            case 'Custom':
                $_object = $this->_getObjectByEntityItemType($_entityItem, 'Invoice')
                    ->getStore();
                break;

            default:
                $_object = null;
                break;
        }

        return $_object;
    }

    /**
     * @param $_entityItem Mage_Sales_Model_Order_Invoice_Item
     */
    protected function _prepareEntityItemObjCustom($_entityItem)
    {
        $_entity       = $this->getEntityByItem($_entityItem);
        $_entityNumber = $this->_getEntityNumber($_entity);
        /** @var Mage_Catalog_Model_Product $product */
        $product       = $this->_getObjectByEntityItemType($_entityItem, 'Product');

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Invoice__c'}
            = $this->_getParentEntityId($_entityNumber);

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Order_Item__c'}
            = $_entityItem->getOrderItem()->getData('salesforce_id');

        $this->_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'disableMagentoSync__c'}
            = true;

        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $this->_obj->CurrencyIsoCode = $this->getCurrencyCode($_entity);
        }

        /* Dump BillingItem object into the log */
        foreach ($this->_obj as $key => $_item) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order Invoice Item Object: " . $key . " = '" . $_item . "'");
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('-----------------');

        $key = $_entityItem->getId();
        // if it's fake product for order fee, has the same id's for all products
        if (!$product->getId()) {
            $key .= '_' . $_entityNumber;
        }

        $this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert']['cart_' . $key] = $this->_obj;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @param $_entityItem Mage_Sales_Model_Order_Invoice_Item
     * @return bool
     */
    protected function _doesCartItemExist($_entity, $_entityItem)
    {
        $_sOrderItemId = $_entityItem->getOrderItem()->getData('salesforce_id');
        $_entityNumber = $this->_getEntityNumber($_entity);
        $lookupKey     = sprintf('%sLookup', $this->_salesforceEntityName);

        if (! ($this->_cache[$lookupKey]
            && array_key_exists($_entityNumber, $this->_cache[$lookupKey])
            && $this->_cache[$lookupKey][$_entityNumber]->Items)
        ){
            return false;
        }

        foreach ($this->_cache[$lookupKey][$_entityNumber]->Items->records as $_cartItem) {
            if ($_cartItem->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Order_Item__c'} != $_sOrderItemId) {
                continue;
            }

            return $_cartItem->Id;
        }

        return false;
    }

    /**
     * @return mixed
     */
    protected function _pushEntity()
    {
        $entityToUpsertKey = sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()));
        if (empty($this->_cache[$entityToUpsertKey])) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('No Invoice found queued for the synchronization!');
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Invoice Push: Start----------');
        foreach ($this->_cache[$entityToUpsertKey] as $_opp) {
            foreach ($_opp as $_key => $_value) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s Object: %s = "%s"', $this->_salesforceEntityName, $_key, $_value));
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("--------------------------");
        }

        $_keys = array_keys($this->_cache[$entityToUpsertKey]);

        try {
            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_before', $this->_magentoEntityName),
                array("data" => $this->_cache[$entityToUpsertKey]));

            $results = $this->getClient()->upsert(
                'Id', array_values($this->_cache[$entityToUpsertKey]), TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT);

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_after', $this->_magentoEntityName), array(
                "data" => $this->_cache[$entityToUpsertKey],
                "result" => $results
            ));
        }
        catch (Exception $e) {
            $results   = array_fill(0, count($_keys),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of an order to Salesforce failed' . $e->getMessage());
        }

        $_undeleteIds = array();
        foreach ($results as $_key => $_result) {
            $_entityNum = $_keys[$_key];

            //Report Transaction
            $this->_cache['responses'][strtolower($this->getManyParentEntityType())][$_entityNum] = $_result;

            if (!$_result->success) {
                if ($_result->errors[0]->statusCode == "ENTITY_IS_DELETED") {
                    $_undeleteIds[] = $_entityNum;
                }

                $this->_processErrors($_result, $this->_salesforceEntityName, $this->_cache[$entityToUpsertKey][$_entityNum]);
                $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_entityNum;

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('%s Failed: (%s: ' . $_entityNum . ')', $this->_salesforceEntityName, $this->_magentoEntityName));
            }
            else {
                $_entity = $this->_loadEntityByCache(array_search($_entityNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_entityNum);
                $_entity->addData(array(
                    'sf_insync'     => 1,
                    'salesforce_id' => (string)$_result->id
                ));
                $_entity->getResource()->save($_entity);

                $this->_cache[sprintf('upserted%s', $this->getManyParentEntityType())][$_entityNum] = $_result->id;
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('%s Upserted: %s' , $this->_salesforceEntityName, $_result->id));
            }
        }

        if (!empty($_undeleteIds)) {
            $_deleted = Mage::helper('tnw_salesforce/salesforce_data_invoice')
                ->lookup($_undeleteIds);

            $_toUndelete = array();
            foreach ($_deleted as $_object) {
                $_toUndelete[] = $_object->Id;
            }

            if (!empty($_toUndelete)) {
                $this->getClient()->undelete($_toUndelete);
            }
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('----------Order Push: End----------');
    }

    /**
     * @param array $chunk
     * @return mixed
     */
    protected function _pushEntityItems($chunk = array())
    {
        $_orderNumbers = array_flip($this->_cache['upserted'.$this->getManyParentEntityType()]);
        $_chunkKeys    = array_keys($chunk);

        try {
            $results = $this->getClient()->upsert(
                'Id', array_values($chunk), TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_ITEM_OBJECT);
        } catch (Exception $e) {
            $results = array_fill(0, count($chunk),
                $this->_buildErrorResponse($e->getMessage()));

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of Order Invoice Items to SalesForce failed' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {
            $_cartItemId = $_chunkKeys[$_key];
            $_invoiceId  = $this->_cache[sprintf('%sToUpsert', lcfirst($this->getItemsField()))][$_cartItemId]
                ->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_INVOICE . 'Invoice__c'};
            $_entityNum  = $_orderNumbers[$_invoiceId];
            $_entity     = $this->_loadEntityByCache(array_search($_entityNum, $this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]), $_entityNum);

            //Report Transaction
            $this->_cache['responses'][lcfirst($this->getItemsField())][$_entityNum]['subObj'][$_cartItemId] = $_result;
            if (!$_result->success) {
                // Reset sync status
                $_entity->setData('sf_insync', 0);
                $_entity->getResource()->save($_entity);

                $this->_processErrors($_result, 'invoiceCart', $chunk[$_cartItemId]);
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('ERROR: One of the Cart Item for (%s: %s) failed to upsert.', $this->_magentoEntityName, $_entityNum));
            }
            else {
                $_item = $_entity->getItemsCollection()->getItemById(str_replace('cart_', '', $_cartItemId));
                if ($_item instanceof Mage_Core_Model_Abstract) {
                    $_item->setData('salesforce_id', $_result->id);
                    $_item->getResource()->save($_item);
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('Cart Item (id: %s) for (%s: %s) upserted.', $_result->id, $this->_magentoEntityName, $_entityNum));
            }
        }
    }

    /**
     * @return bool
     */
    protected function isNotesEnabled()
    {
        return Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoiceNotes();
    }

    /**
     * @param $notes Mage_Sales_Model_Order_Invoice_Comment
     * @throws Exception
     */
    protected function _getNotesParentSalesforceId($notes)
    {
        return $notes->getInvoice()->getSalesforceId();
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @throws Exception
     * @return array
     */
    protected function _getEntityNotesCollection($_entity)
    {
        return $_entity->getCommentsCollection();
    }

    /**
     * @return string
     */
    protected function _notesTableName()
    {
        return Mage::helper('tnw_salesforce')->getTable('sales_flat_invoice_comment');
    }

    /**
     * @comment return parent entity items
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @return mixed
     */
    public function getItems($_entity)
    {
        $_itemCollection = $_entity->getItemsCollection();
        $_hasOrderItemId = $_itemCollection->walk('getOrderItemId');

        $items = parent::getItems($_entity);
        /** @var Mage_Sales_Model_Order_Invoice_Item $item */
        foreach ($_itemCollection as $item) {
            if ($item->isDeleted() || $item->getOrderItem()->getParentItem()) {
                continue;
            }

            $item = clone $item;
            if ($item->getOrderItem()->getProductType() != Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                $items[] =  $item;
                continue;
            }

            $item
                ->setTaxAmount(null)
                ->setBaseTaxAmount(null)
                ->setHiddenTaxAmount(null)
                ->setBaseHiddenTaxAmount(null)
                ->setRowTotal(null)
                ->setBaseRowTotal(null)
                ->setDiscountAmount(null)
                ->setBaseDiscountAmount(null);

            switch (Mage::getStoreConfig(TNW_Salesforce_Helper_Config_Sales::XML_PATH_ORDERS_BUNDLE_ITEM_SYNC)) {
                case 0:
                    //Add parent
                    $items[] = $item;

                    /** @var Mage_Sales_Model_Order_Item $_orderItem */
                    foreach ($item->getOrderItem()->getChildrenItems() as $_orderItem) {
                        $_itemId = array_search($_orderItem->getId(), $_hasOrderItemId);

                        if (!$_itemId) {
                            continue;
                        }

                        $_item   = clone $_itemCollection->getItemById($_itemId);
                        if (!$_item instanceof Mage_Sales_Model_Order_Invoice_Item) {
                            continue;
                        }

                        $item
                            ->setTaxAmount($item->getTaxAmount() + $_item->getTaxAmount())
                            ->setBaseTaxAmount($item->getBaseTaxAmount() + $_item->getBaseTaxAmount())
                            ->setHiddenTaxAmount($item->getHiddenTaxAmount() + $_item->getHiddenTaxAmount())
                            ->setBaseHiddenTaxAmount($item->getBaseHiddenTaxAmount() + $_item->getBaseHiddenTaxAmount())
                            ->setRowTotal($item->getRowTotal() + $_item->getRowTotal())
                            ->setBaseRowTotal($item->getBaseRowTotal() + $_item->getBaseRowTotal())
                            ->setDiscountAmount($item->getDiscountAmount() + $_item->getDiscountAmount())
                            ->setBaseDiscountAmount($item->getBaseDiscountAmount() + $_item->getBaseDiscountAmount());
                    }
                    break;

                case 1:
                    //Add parent
                    $items[] = $item;

                    //Add children
                    /** @var Mage_Sales_Model_Order_Item $_orderItem */
                    foreach ($item->getOrderItem()->getChildrenItems() as $_orderItem) {
                        $_itemId = array_search($_orderItem->getId(), $_hasOrderItemId);

                        if (!$_itemId) {
                            continue;
                        }

                        $_item   = clone $_itemCollection->getItemById($_itemId);
                        if (!$_item instanceof Mage_Sales_Model_Order_Invoice_Item) {
                            continue;
                        }

                        $item
                            ->setTaxAmount($item->getTaxAmount() + $_item->getTaxAmount())
                            ->setBaseTaxAmount($item->getBaseTaxAmount() + $_item->getBaseTaxAmount())
                            ->setHiddenTaxAmount($item->getHiddenTaxAmount() + $_item->getHiddenTaxAmount())
                            ->setBaseHiddenTaxAmount($item->getBaseHiddenTaxAmount() + $_item->getBaseHiddenTaxAmount())
                            ->setRowTotal($item->getRowTotal() + $_item->getRowTotal())
                            ->setBaseRowTotal($item->getBaseRowTotal() + $_item->getBaseRowTotal())
                            ->setDiscountAmount($item->getDiscountAmount() + $_item->getDiscountAmount())
                            ->setBaseDiscountAmount($item->getBaseDiscountAmount() + $_item->getBaseDiscountAmount());

                        $_item
                            ->setTaxAmount(null)
                            ->setBaseTaxAmount(null)
                            ->setHiddenTaxAmount(null)
                            ->setBaseHiddenTaxAmount(null)
                            ->setRowTotal(null)
                            ->setBaseRowTotal(null)
                            ->setDiscountAmount(null)
                            ->setBaseDiscountAmount(null)
                            ->setBundleItemToSync(TNW_Salesforce_Helper_Config_Sales::BUNDLE_ITEM_MARKER
                                . $item->getSku());

                        $items[] = $_item;
                    }
                    break;

                case 2:
                    //Add children
                    /** @var Mage_Sales_Model_Order_Item $_orderItem */
                    foreach ($item->getOrderItem()->getChildrenItems() as $_orderItem) {
                        $_itemId = array_search($_orderItem->getId(), $_hasOrderItemId);

                        if (!$_itemId) {
                            continue;
                        }
                        
                        $_item   = clone $_itemCollection->getItemById($_itemId);
                        if (!$_item instanceof Mage_Sales_Model_Order_Invoice_Item) {
                            continue;
                        }

                        $_item->setBundleItemToSync(TNW_Salesforce_Helper_Config_Sales::BUNDLE_ITEM_MARKER
                            . $item->getSku());

                        $items[] = $_item;
                    }
                    break;
            }
        }

        return $items;
    }

    /**
     * Clean up all the data & memory
     */
    protected function _onComplete()
    {
        parent::_onComplete();

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            /** @var TNW_Salesforce_Helper_Report $logger */
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();

            $logger->add('Salesforce', ucwords($this->_magentoEntityName),
                $this->_cache[sprintf('%sToUpsert', strtolower($this->getManyParentEntityType()))],
                $this->_cache['responses'][strtolower($this->getManyParentEntityType())]);

            $logger->add('Salesforce', ucwords($this->_magentoEntityName) . 'Item',
                $this->_cache[sprintf('%sToUpsert', lcfirst($this->getItemsField()))],
                $this->_cache['responses'][lcfirst($this->getItemsField())]);

            if (!empty($this->_cache['responses']['notes'])) {
                $logger->add('Salesforce', 'Note', $this->_cache['notesToUpsert'], $this->_cache['responses']['notes']);
            }

            $logger->send();
        }

        // Logout
        $this->reset();
        $this->clearMemory();
    }

    /**
     * @return bool|void
     * Prepare values for the synchroization
     */
    public function reset()
    {
        parent::reset();

        // Clean order cache
        if (is_array($this->_cache['entitiesUpdating'])) {
            foreach ($this->_cache['entitiesUpdating'] as $_key => $_orderNumber) {
                $this->unsetEntityCache($_orderNumber);
            }
        }

        $this->_cache = array(
            'accountsLookup' => array(),
            'entitiesUpdating' => array(),
            sprintf('upserted%s', $this->getManyParentEntityType()) => array(),
            sprintf('failed%s', $this->getManyParentEntityType()) => array(),
            sprintf('%sToUpsert', lcfirst($this->getItemsField())) => array(),
            sprintf('%sToUpsert', strtolower($this->getManyParentEntityType())) => array(),
        );

        return $this->check();
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @param $item Varien_Object
     */
    protected function _prepareAdditionalFees($_entity, $item)
    {
        /** @var Mage_Sales_Model_Order_Item $_orderItem */
        $_orderItem           = Mage::getModel('sales/order_item');

        $orderLookup = @$this->_cache['orderLookup'][$_entity->getOrder()->getRealOrderId()];
        if ($orderLookup && property_exists($orderLookup, 'OrderItems') && $orderLookup->OrderItems) {
            foreach ($orderLookup->OrderItems->records as $record) {
                if ($record->PricebookEntry->Product2Id != $item->getData('Id')) {
                    continue;
                }

                $_orderItem->setData('salesforce_id', $record->Id);
                break;
            }
        }

        //FIX: $item->getOrderItem()->getData('salesforce_id')
        $item->setData('order_item', $_orderItem);
    }
}