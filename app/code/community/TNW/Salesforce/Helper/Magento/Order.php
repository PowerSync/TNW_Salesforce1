<?php

class TNW_Salesforce_Helper_Magento_Order extends TNW_Salesforce_Helper_Magento_Abstract
{
    /**
     * @param stdClass $object
     * @return mixed
     */
    public function syncFromSalesforce($object = null)
    {
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
     * @return Mage_Sales_Model_Order|bool
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
        }
        else {
            if (!Mage::helper('tnw_salesforce')->isOrderCreateReverseSync()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('Creating orders with reverse sync disabled');

                return false;
            }

            /** @var Mage_Adminhtml_Model_Sales_Order_Create $orderCreate */
            $orderCreate   = Mage::getSingleton('adminhtml/sales_order_create')
                ->setIsValidate(false);
            /* @var $productHelper Mage_Catalog_Helper_Product */
            $productHelper = Mage::helper('catalog/product');

            // Get Customer
            $customer = $this->_searchCustomer($object->BillToContactId);
            if (is_null($customer->getId())) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Customer Not Found');

                return false;
            }

            $_websiteId = null;
            $_websiteSfField = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();
            if (property_exists($object, $_websiteSfField)) {
                $_websiteSfId = Mage::helper('tnw_salesforce')
                    ->prepareId($object->{$_websiteSfField});

                $_websiteId = array_search($_websiteSfId, $this->_websiteSfIds);
                if ($_websiteId === false) {
                    $_websiteId = null;
                }
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
            $items = array();
            foreach ($object->OrderItems->records as $record) {
                $product = $this->_searchProduct($record->PricebookEntry->Product2Id);
                if (is_null($product->getId())) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveNotice('Product (sku:'.$record->PricebookEntry->Product2->ProductCode.') not found');

                    continue;
                }

                if ($product->getTypeId() != Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveNotice('Product (sku:'.$record->PricebookEntry->Product2->ProductCode.') skipping');

                    continue;
                }

                $items[$product->getId()] = array('qty'=>$record->Quantity);
                $buyRequest = new Varien_Object($items[$product->getId()]);
                $params = array('files_prefix' => 'item_' . $product->getId() . '_');
                $buyRequest = $productHelper->addParamsToBuyRequest($buyRequest, $params);
                if ($buyRequest->hasData()) {
                    $items[$product->getId()] = $buyRequest->toArray();
                }
            }

            if (empty($items)) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('Empty products');

                return false;
            }

            $orderCreate->addProducts($items);

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

            // Payment
            $orderCreate->setPaymentData(array(
                'method' => 'tnw_import'
            ));

            try {
                $order = $orderCreate->createOrder();
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

                return false;
            }
        }

        $order->addData(array(
            'salesforce_id' => $_sSalesforceId,
            'sf_insync'     => 1
        ));

        $this->_updateMappedEntityFields($object, $order, $mappings)
            ->_updateStatus($object, $order)
            ->saveEntities();

        return $order;
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
            'Shipping'  => $order->getShippingAddress(),
            'Billing'   => $order->getBillingAddress(),
            'Payment'   => $order->getPayment(),
            'Customer'  => Mage::getModel('customer/customer')->load($order->getCustomerId()),
        );

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

            if ($entity->hasData($field) && $entity->getData($field) != $newValue) {
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
     * @param $object
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
}