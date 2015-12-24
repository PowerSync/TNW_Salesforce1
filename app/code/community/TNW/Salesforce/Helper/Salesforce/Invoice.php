<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Invoice
 *
 * @method Mage_Sales_Model_Order_Invoice _loadEntityByCache($_entityId, $_entityNumber)
 */
class TNW_Salesforce_Helper_Salesforce_Invoice extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
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
     *
     */
    protected function _massAddBefore()
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

        $_recordNumber =  $this->_getEntityNumber($_entity);

        $_cacheCustomers = sprintf('%sCustomers', $this->_magentoEntityName);
        // Get Magento customer object
        $this->_cache[$_cacheCustomers][$_recordNumber] = $this->_getCustomer($_order);

        // Associate order Number with a customer ID
        $_customerId = $this->_cache[sprintf('%sToCustomerId', $this->_magentoEntityName)][$_recordNumber]
            = ($this->_cache[$_cacheCustomers][$_recordNumber]->getId())
                ? $this->_cache[$_cacheCustomers][$_recordNumber]->getId() : sprintf('guest-%d', $this->_guestCount++);

        // Associate order Number with a customer Email
        $this->_cache[sprintf('%sToEmail', $this->_magentoEntityName)][$_recordNumber]
            = strtolower($this->_cache[$_cacheCustomers][$_recordNumber]->getEmail());
        if (empty($this->_cache[sprintf('%sToEmail', $this->_magentoEntityName)][$_recordNumber]) ) {
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

        // Check if customer from this group is allowed to be synchronized
        $_customerGroup = $_order->getData('customer_group_id');
        if ($_customerGroup === NULL) {
            $_customerGroup = $this->_cache[$_cacheCustomers][$_recordNumber]->getGroupId();
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

        $_websiteId = ($this->_cache[$_cacheCustomers][$_recordNumber]->getData('website_id'))
            ? $this->_cache[$_cacheCustomers][$_recordNumber]->getData('website_id')
            : Mage::getModel('core/store')->load($_entity->getData('store_id'))->getWebsiteId();

        $this->_emails[$_customerId]   = strtolower($this->_cache[$_cacheCustomers][$_recordNumber]->getEmail());
        $this->_websites[$_customerId] = $this->_websiteSfIds[$_websiteId];

        return true;
    }

    /**
     * Sync customer w/ SF before creating the order
     *
     * @param $order Mage_Core_Model_Abstract|Mage_Sales_Model_Order|Mage_Sales_Model_Quote
     * @return false|Mage_Core_Model_Abstract
     */
    protected function _getCustomer($order)
    {
        $customer_id = $order->getCustomerId();
        if (!$customer_id && !$this->isFromCLI()) {
            Mage::getSingleton('customer/session')->getCustomerId();
        }

        if ($customer_id) {
            $_customer = Mage::getModel("customer/customer");
            if (Mage::helper('tnw_salesforce')->getMagentoVersion() < 1500) {
                $sql = "SELECT website_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` WHERE entity_id = '" . $customer_id . "'";
                $row = Mage::helper('tnw_salesforce')->getDbConnection()->query($sql)->fetch();
                if (!$row) {
                    $_customer->setWebsiteId($row['website_id']);
                }
            }
            $_customer = $_customer->load($customer_id);
            unset($customer_id);
        } else {
            // Guest most likely
            $_customer = Mage::getModel('customer/customer');

            $_websiteId = Mage::getModel('core/store')->load($order->getStoreId())->getWebsiteId();
            $_storeId = $order->getStoreId();
            if ($_customer->getSharingConfig()->isWebsiteScope()) {
                $_customer->setWebsiteId($_websiteId);
            }
            $_email = strtolower($order->getCustomerEmail());
            $_customer->loadByEmail($_email);

            if (!$_customer->getId()) {
                //Guest
                $_customer = Mage::getModel("customer/customer");
                $_customer->setGroupId(0); // NOT LOGGED IN
                $_customer->setFirstname($order->getBillingAddress()->getFirstname());
                $_customer->setLastname($order->getBillingAddress()->getLastname());
                $_customer->setEmail($_email);
                $_customer->setStoreId($_storeId);
                if (isset($_websiteId)) {
                    $_customer->setWebsiteId($_websiteId);
                }

                $_customer->setCreatedAt(gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($order->getCreatedAt()))));
                //TODO: Extract as much as we can from the order

            } else {

                $sql = '';
                //UPDATE order to record Customer Id
                if ($order->getResource()->getMainTable()) {

                    $sql = "UPDATE `" . $order->getResource()->getMainTable() . "` SET customer_id = " . $_customer->getId() . " WHERE entity_id = " . $order->getId() . ";";
                }

                if ($order->getResource()->getGridTable()) {
                    $sql .= "UPDATE `" . $order->getResource()->getGridTable() . "` SET customer_id = " . $_customer->getId() . " WHERE entity_id = " . $order->getId() . ";";
                }

                if ($order->getAddressesCollection()->getMainTable()) {
                    $sql .= "UPDATE `" . $order->getAddressesCollection()->getMainTable() . "` SET customer_id = " . $_customer->getId() . " WHERE parent_id = " . $order->getId() . ";";
                }
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);
                Mage::helper("tnw_salesforce")->log('Guest user found in Magento, updating order #' . $order->getId() . ' attaching cusomter ID: ' . $_customer->getId());
            }
        }
        if (
            !$_customer->getDefaultBillingAddress()
            && is_object($order->getBillingAddress())
            && $order->getBillingAddress()->getData()
        ) {
            $_billingAddress = Mage::getModel('customer/address');
            $_billingAddress->setCustomerId(0)
                ->setIsDefaultBilling('1')
                ->setSaveInAddressBook('0')
                ->addData($order->getBillingAddress()->getData());
            $_customer->setBillingAddress($_billingAddress);
        }
        if (
            !$_customer->getDefaultShippingAddress()
            && is_object($order->getShippingAddress())
            && $order->getShippingAddress()->getData()
        ) {
            $_shippingAddress = Mage::getModel('customer/address');
            $_shippingAddress->setCustomerId(0)
                ->setIsDefaultShipping('1')
                ->setSaveInAddressBook('0')
                ->addData($order->getShippingAddress()->getData());
            $_customer->setShippingAddress($_shippingAddress);
        }

        $_websiteId = Mage::getModel('core/store')->load($order->getStoreId())->getWebsiteId();
        if ($_customer->getSharingConfig()->isWebsiteScope()) {
            $_customer->setWebsiteId($_websiteId);
        }

        // Set Company Name
        if (!$_customer->getData('company') && $order->getBillingAddress()->getData('company')) {
            $_customer->setData('company', $order->getBillingAddress()->getData('company'));
        } elseif (!$_customer->getData('company') && !Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $_customer->setData('company', $_customer->getFirstname() . ' ' . $_customer->getLastname());
        }

        return $_customer;
    }

    /**
     *
     */
    protected function _massAddAfter()
    {
        // Salesforce lookup, find all contacts/accounts by email address
        $this->_cache['accountsLookup'] = Mage::helper('tnw_salesforce/salesforce_data_contact')
            ->lookup($this->_emails, $this->_websites);

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
     * @param $_entity Mage_Sales_Model_Order_Invoice
     */
    protected function _prepareEntityItemAfter($_entity)
    {
        $this->_applyAdditionalFees($_entity);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @return mixed
     */
    protected function _setEntityInfo($_entity)
    {
        $_websiteId     = Mage::getModel('core/store')->load($_entity->getStoreId())->getWebsiteId();
        $_entityNumber  = $this->_getEntityNumber($_entity);
        $_customer      = $this->_cache[sprintf('%sCustomers', $this->_magentoEntityName)][$_entityNumber];
        $_lookupKey     = sprintf('%sLookup', $this->_salesforceEntityName);

        if (isset($this->_cache[$_lookupKey][$_entityNumber])) {
            $this->_obj->Id = $this->_cache[$_lookupKey][$_entityNumber]->Id;
        }

        $this->_obj->Name = $_entityNumber;

        // Link to a Website
        if (
            $_websiteId != NULL
            && array_key_exists($_websiteId, $this->_websiteSfIds)
            && $this->_websiteSfIds[$_websiteId]
        ) {
            $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Magento_Website__c'}
                = $this->_websiteSfIds[$_websiteId];
        }

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Invoice_Date__c'}
            = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($_entity->getCreatedAtDate())));

        // Link to Order
        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Order__c'}
            = $_entity->getOrder()->getData('salesforce_id');

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Magento_ID__c'}
            = $_entityNumber;

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Status__c'}
            = $_entity->getStateName();

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Total__c'}
            = $_entity->getGrandTotal();

        // Account ID
        $_customerSFAccountId = (is_object($_customer) && $_customer->getSalesforceAccountId())
            ? $_customer->getSalesforceAccountId()
            : $_entity->getOrder()->getData('account_salesforce_id');

        // For guest, extract converted Account Id
        if (!$_customerSFAccountId) {
            $_customerSFAccountId = (
                isset($this->_cache['convertedLeads'])
                && isset($this->_cache['convertedLeads'][$_entityNumber])
                && property_exists($this->_cache['convertedLeads'][$_entityNumber], 'accountId')
            ) ? $this->_cache['convertedLeads'][$_entityNumber]->accountId : NULL;
        }

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Account__c'}
            = $_customerSFAccountId;

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Description__c'}
            = $this->_getDescriptionByEntity($_entity);

        $_customerSFContactId = (is_object($_customer) && $_customer->getSalesforceContactId())
            ? $_customer->getSalesforceContactId()
            : $_entity->getOrder()->getData('contact_salesforce_id');

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Billing_Contact__c'}
            = $_customerSFContactId;

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Shipping_Contact__c'}
            = $_customerSFContactId;

        //Process mapping
        Mage::getSingleton('tnw_salesforce/sync_mapping_invoice_orderinvoice')
            ->setSync($this)
            ->processMapping($_entity);
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @param $_entityItem Mage_Sales_Model_Order_Invoice_Item
     * @return mixed|void
     */
    protected function _prepareEntityItemObj($_entity, $_entityItem)
    {
        $_entityNumber = $this->_getEntityNumber($_entity);
        $_quantity     = $this->getItemQty($_entityItem);

        $this->_obj = new stdClass();

        // Load by product Id only if bundled OR simple with options
        $_productId    = $this->getProductIdFromCart($_entityItem);
        if ($_productId) {
            /** @var $_product Mage_Catalog_Model_Product */
            $_product = Mage::getModel('catalog/product')
                ->setStoreId($_entity->getStoreId())
                ->load($_productId);

            $this->_getDescriptionByEntityItem($_entity, $_entityItem->getOrderItem(), $_description, $_productOptions);
            $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Description__c'}
                = $_description;

            $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Product_Options__c'}
                = $_productOptions;

            $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Magento_ID__c'}
                = $_entityItem->getId();

            $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Order_Item__c'}
                = $_entityItem->getOrderItem()->getData('salesforce_id');

            //Process mapping
            Mage::getSingleton('tnw_salesforce/sync_mapping_invoice_orderinvoice_item')
                ->setSync($this)
                ->processMapping($_entityItem, $_product);
        }
        else {
            // Fees item
            $_product = new Varien_Object();
            $_product->addData(array(
                'name'  => $_entityItem->getData('Name'),
                'sku'   => $_entityItem->getData('ProductCode'),
            ));

            $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Description__c'}
                = $_entityItem->getDescription();

            $orderLookup = @$this->_cache['orderLookup'][$_entity->getOrder()->getRealOrderId()];
            if (! ($orderLookup && property_exists($orderLookup, 'OrderItems') && $orderLookup->OrderItems)) {
                return;
            }

            foreach ($orderLookup->OrderItems->records as $record) {
                if ($record->PricebookEntry->ProductCode != $_product->getSku()) {
                    continue;
                }

                $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Order_Item__c'}
                    = $record->Id;

                break;
            }
        }

        $cartItemFound = $this->_doesCartItemExist($_entity, $_entityItem, $_product);
        if ($cartItemFound) {
            $this->_obj->Id = $cartItemFound;
        }

        $this->_obj->Name = $_product->getName();

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Invoice__c'}
            = $this->_getParentEntityId($_entityNumber);

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Product_Code__c'}
            = $_product->getSku();

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Quantity__c'}
            = $_quantity;

        $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Total__c'}
            =  $this->_prepareItemPrice($_entityItem);

        if (!$this->isItemObjectValid()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING: Order Invoice item failed validation!');
            return;
        }

        /* Dump BillingItem object into the log */
        foreach ($this->_obj as $key => $_item) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order Invoice Item Object: " . $key . " = '" . $_item . "'");
        }

        $this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert']['cart_' . $_entityItem->getId()] = $this->_obj;
    }

    /**
     * @param $_entity Mage_Sales_Model_Order_Invoice
     * @param $_entityItem Mage_Sales_Model_Order_Invoice_Item
     * @param $_product Mage_Catalog_Model_Product
     * @return bool
     */
    protected function _doesCartItemExist($_entity, $_entityItem, $_product)
    {
        $_quantity     = $this->getItemQty($_entityItem);
        $_entityNumber = $this->_getEntityNumber($_entity);
        $lookupKey     = sprintf('%sLookup', $this->_salesforceEntityName);

        if (! ($this->_cache[$lookupKey]
            && array_key_exists($_entityNumber, $this->_cache[$lookupKey])
            && $this->_cache[$lookupKey][$_entityNumber]->Items)
        ){
            return false;
        }

        foreach ($this->_cache[$lookupKey][$_entityNumber]->Items->records as $_cartItem) {
            if (
                $_cartItem->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Product_Code__c'} == $_product->getSku()
                && $_cartItem->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Quantity__c'} == (float)$_quantity
            ) {
                /**
                 * if current SF item already assigned to some Magento item - skip it and try to find one more
                 * sometimes items with the same parameters can be in order - we should divide it
                 */
                foreach ($this->_cache[lcfirst($this->getItemsField()) . 'ToUpsert'] as $itemToUpsert) {
                    if (property_exists($itemToUpsert, 'Id')
                        && !empty($itemToUpsert->Id)
                        && $_cartItem->Id == $itemToUpsert->Id
                    ) {
                        continue 2;
                    }
                }

                return $_cartItem->Id;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function isItemObjectValid()
    {
        return (property_exists($this->_obj, /*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Order_Item__c')
            && $this->_obj->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Order_Item__c'});
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

            $results = $this->_mySforceConnection->upsert(
                'Id', array_values($this->_cache[$entityToUpsertKey]), TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT);

            Mage::dispatchEvent(sprintf('tnw_salesforce_%s_send_after', $this->_magentoEntityName), array(
                "data" => $this->_cache[$entityToUpsertKey],
                "result" => $results
            ));
        }
        catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach ($_keys as $_id) {
                $this->_cache['responses'][strtolower($this->getManyParentEntityType())][$_id] = $_response;
            }

            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of an order to Salesforce failed' . $e->getMessage());
        }

        $_entityArray = array_flip($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);
        $_undeleteIds = array();
        if (!$results) {
            $results = array();
        }

        foreach ($results as $_key => $_result) {
            $_entityNum = $_keys[$_key];

            //Report Transaction
            $this->_cache['responses'][strtolower($this->getManyParentEntityType())][$_entityNum] = $_result;

            if (!$_result->success) {
                if ($_result->errors[0]->statusCode == "ENTITY_IS_DELETED") {
                    $_undeleteIds[] = $_entityNum;
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('%s Failed: (%s: ' . $_entityNum . ')', $this->_salesforceEntityName, $this->_magentoEntityName));

                $this->_processErrors($_result, $this->_salesforceEntityName, $this->_cache[$entityToUpsertKey][$_entityNum]);
                $this->_cache[sprintf('failed%s', $this->getManyParentEntityType())][] = $_entityNum;
            }
            else {
                $sql = sprintf('UPDATE `%s` SET sf_insync = 1, salesforce_id = "%s" WHERE entity_id = %d;',
                    $this->_modelEntity()->getResource()->getMainTable(), $_result->id, $_entityArray[$_entityNum]);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

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
                $this->_mySforceConnection->undelete($_toUndelete);
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
            $results = $this->_mySforceConnection->upsert(
                'Id', array_values($chunk), TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_ITEM_OBJECT);
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach ($chunk as $_object) {
                $this->_cache['responses'][lcfirst($this->getItemsField())][] = $_response;
            }
            $results = array();
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError('CRITICAL: Push of Order Invoice Items to SalesForce failed' . $e->getMessage());
        }

        foreach ($results as $_key => $_result) {
            $_cartItemId = $_chunkKeys[$_key];
            $_invoiceId  = $this->_cache[sprintf('%sToUpsert', lcfirst($this->getItemsField()))][$_cartItemId]
                ->{/*TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL .*/ 'Invoice__c'};
            $_orderNum   = $_orderNumbers[$_invoiceId];

            //Report Transaction
            $this->_cache['responses'][lcfirst($this->getItemsField())][] = $_result;
            if (!$_result->success) {
                // Reset sync status
                $sql = sprintf('UPDATE `%s` SET sf_insync = 0 WHERE salesforce_id = "%s";',
                    $this->_modelEntity()->getResource()->getMainTable(), $_invoiceId);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $sql);
                Mage::helper('tnw_salesforce')->getDbConnection()->query($sql);

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('ERROR: One of the Cart Item for (%s: %s) failed to upsert.', $this->_magentoEntityName, $_orderNum));
                $this->_processErrors($_result, 'invoiceCart', $chunk[$_cartItemId]);
            }
            else {
                if ($_cartItemId && strrpos($_cartItemId, 'cart_', -strlen($_cartItemId)) !== FALSE) {
                    $_sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_invoice_item') . "` SET salesforce_id = '" . $_result->id . "' WHERE entity_id = '" . str_replace('cart_', '', $_cartItemId) . "';";
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SQL: ' . $_sql);
                    Mage::helper('tnw_salesforce')->getDbConnection()->query($_sql);
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace(sprintf('Cart Item (id: %s) for (%s: %s) upserted.', $_result->id, $this->_magentoEntityName, $_orderNum));
            }
        }
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
        return $_entity->getAllItems();
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
}