<?php

/**
 * Class TNW_Salesforce_Helper_Order
 */
class TNW_Salesforce_Helper_Order extends TNW_Salesforce_Helper_Abstract
{
    const ZERO_ORDER_SYNC = 'salesforce_order/general/zero_order_sync_enable';

    public function isEnabledZeroOrderSync(){
        return $this->getStroreConfig(self::ZERO_ORDER_SYNC);
    }

    /**
     * @param $_item
     * @return int
     * Get product Id from the cart
     */
    public function getProductIdFromCart($_item) {
        $_options = unserialize($_item->getData('product_options'));
        if(
            is_array($_options)
            && (
                $_item->getData('product_type') == 'bundle'
                || array_key_exists('options', $_options)
            )
        ) {
            $id = $_item->getData('product_id');
        } else {
            $id = (int)Mage::getModel('catalog/product')->getIdBySku($_item->getSku());
        }
        return $id;
    }

/* OLD CRAP, too much magic in here and not used. Need to clean up */

    /**
     * @var null
     */
    protected $_fromAccount = NULL;

    /**
     * @var null
     */
    protected $_orderId = NULL;

    /**
     * @var null
     */
    protected $_orderRealId = NULL;

    /**
     * @var bool
     */
    protected $_isNew = TRUE;

    /**
     * @var bool
     */
    protected $_isManual = TRUE;

    /**
     * @var bool
     */
    protected $_isStageUpdate = FALSE;

    /**
     * @var bool
     */
    protected $_syncShipments = FALSE;

    /**
     * @var null
     */
    protected $_lead = NULL;

    /**
     * @var null
     */
    protected $_contactId = NULL;

    /**
     * @var null
     */
    protected $_sfOrderId = NULL;

    /**
     * @var null
     */
    protected $_mySforceConnection = NULL;

    /**
     * @var null
     */
    protected $_customer = NULL;

    protected function prepare()
    {
        $this->_orderId = NULL;
        $this->_orderRealId = NULL;
        $this->_isNew = NULL;
        $this->_lead = NULL;
        $this->_contactId = NULL;
        $this->_sfOrderId = NULL;
        $this->_customer = NULL;
    }

    protected function checkConnection()
    {
        /* Initiate connection */
        try {
            $this->_mySforceConnection = Mage::helper('tnw_salesforce/salesforce_data')->getClient();
            if (!$this->_mySforceConnection) {
                Mage::helper('tnw_salesforce')->log("SKIPPING: Salesforce connection failed!");
                return;
            }
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Could not get Salesforce connection");
            Mage::helper('tnw_salesforce')->log("ERROR:" . $e->getMessage());

            return;
        }
    }

    /**
     * process order and push to salesforce
     *
     * @param $order
     */
    protected function processOrder($order)
    {
        ## Prepare Opportunity
        Mage::helper('tnw_salesforce')->log("------------------- Opportunity Start -------------------");

        $this->_setOpportunityInfo($order);

        // Sync Order
        $this->opportunityPush($order);

        Mage::helper('tnw_salesforce')->log("------------------- Opportunity End -------------------");
    }

    /**
     * read order and push as an opportunity
     *
     * @param $order
     * @return bool
     */
    protected function opportunityPush($order)
    {
        $upsertOn = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';
        Mage::helper('tnw_salesforce')->log("Upserting on: " . $upsertOn);

        if (
            !$this->_isStageUpdate && (
                !property_exists($this->_lead, 'Name')
                || empty($this->_lead->Name)
                || !property_exists($this->_lead, 'CloseDate')
                || empty($this->_lead->CloseDate)
            )
        ) {
            Mage::helper('tnw_salesforce')->log('Opportunity Name or CloseDate is not set');
            Mage::helper('tnw_salesforce')->log("SKIPPING! Opportunity update: " . $order->getSalesforceId());
            return true;
        }

        if (Mage::helper('tnw_salesforce')->getApiType() == "Partner") {
            $sObject = new SObject();
            $sObject->fields = (array)$this->_lead;
            $sObject->type = 'Opportunity';
            Mage::dispatchEvent("tnw_salesforce_opportunity_send_before",array("data" => array($sObject)));
            $upsertOpportunityResponse = $this->_mySforceConnection->upsert($upsertOn, array($sObject));
            Mage::dispatchEvent("tnw_salesforce_opportunity_send_after",array(
                "data" => array($sObject),
                "result" => $upsertOpportunityResponse
            ));
            unset($sObject);
        } else {
            Mage::dispatchEvent("tnw_salesforce_opportunity_send_before",array("data" => array($this->_lead)));
            $upsertOpportunityResponse = $this->_mySforceConnection->upsert($upsertOn, array($this->_lead), 'Opportunity');
            Mage::dispatchEvent("tnw_salesforce_opportunity_send_after",array(
                "data" => array($this->_lead),
                "result" => $upsertOpportunityResponse
            ));
        }

        $result = (is_array($upsertOpportunityResponse)) ? $upsertOpportunityResponse[0] : $upsertOpportunityResponse;
        if (!$result->success) {
            Mage::helper('tnw_salesforce')->log("Error upserting the Opportunity");
            $errors = (is_array($result->errors)) ? $result->errors : array($result->errors);
            foreach ($errors as $_error) {
                Mage::helper('tnw_salesforce')->log("Error: " . $_error->message);
            }
            Mage::helper('tnw_salesforce/email')->sendError($errors[0]->message, $this->_lead);
            return;
        } else {
            $this->_sfOrderId = $result->id;
            if (!$this->_isStageUpdate) {
                /* Continue only if new */
                if ($this->_isNew) {

                    $this->_customerRolePush();
                    $this->_cartPush($order, $result->id);

                } else {
                    Mage::helper('tnw_salesforce')->log("Order is not new! SKIPPING cart creation");
                    Mage::helper('tnw_salesforce')->log("Order SF ID: " . $order->getSalesforceId());
                }

                // Update order with Salesforce ID
                $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET salesforce_id = '" . $result->id . "' WHERE entity_id = " . $this->_orderId . ";";
                Mage::getSingleton('core/resource')->getConnection('core_write')->query($sql);
            }
            Mage::helper('tnw_salesforce')->log("Opportunity upserted: " . $order->getSalesforceId());

            unset($order);
        }
        unset($upsertOpportunityResponse, $result, $upsertOn);

    }

    /**
     * Get order object and update Opportunity Status in Salesforce
     *
     * @param $order
     */
    public function updateStatus($order)
    {
        $this->checkConnection();
        $this->_isStageUpdate = TRUE;

        $this->_lead = new stdClass();
        // Magento Order ID
        $orderIdParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $this->_lead->$orderIdParam = $order->getRealOrderId();

        // possible place to add some mapping logic call, postponed for now
        //Mage::helper('tnw_salesforce/salesforce_opportunity')->_setOpportunityInfo($order);
        $this->_updateOrderStageName($order);

        // Only update for existing opportunities
        // TODO check if the order already exists in the queue
        if (!Mage::getModel('tnw_salesforce/localstorage')->getObject()) {
            // TODO if the order does not exists in the queue table - just add it
        }

        // start of comments: this code should be commented just queue functionality will be added
        // realtime sf sync
        if ($order->getSalesforceId()) {
            $this->opportunityPush($order);
        } else {
            //Mage::helper('tnw_salesforce')->log("Skipping Status update, most likely still in quote stage!");
        }
        // end of comments

        $this->_isStageUpdate = FALSE;
    }

    /**
     * @param $order
     *
     * Sync customer w/ SF before creating the order
     */
    protected function updateCustomer($order)
    {
        $customer_id = ($order->getCustomerId()) ? $order->getCustomerId() : Mage::getSingleton('customer/session')->getCustomerId();
        $isGuest = false;
        if ($customer_id) {
            Mage::helper('tnw_salesforce')->log("Customer logged in, loading by ID");
            $this->_customer = Mage::getModel("customer/customer")->load($customer_id);
            unset($customer_id);
        } else {
            // Guest most likely
            $this->_customer = Mage::getModel('customer/customer');
            $this->_customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
            $this->_customer->loadByEmail($order->getCustomerEmail());
            if (!$this->_customer->getId()) {
                Mage::helper('tnw_salesforce')->log("Guest customer, presetting customer values...");
                //Guest
                $this->_customer->setGroupId(0); // NOT LOGGED IN
                $this->_customer->setFirstname($order->getBillingAddress()->getFirstname());
                $this->_customer->setLastname($order->getBillingAddress()->getLastname());
                $this->_customer->setEmail($order->getCustomerEmail());
                $isGuest = true;
            } else {
                Mage::helper('tnw_salesforce')->log("Customer loaded by email");
            }
        }
        $this->_customer = Mage::helper('tnw_salesforce/customer')->pushContact($this->_customer, $order, false, false, $isGuest);
    }

    /**
     * @param $order
     * Create Opportunity object
     */
    protected function _setOpportunityInfo($order)
    {
        $this->_updateOrderStageName($order);

        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $syncParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix('enterprise') . "disableMagentoSync__c";
            $this->_lead->$syncParam = true;
        }
        // Magento Order ID
        $orderIdParam = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        $this->_lead->$orderIdParam = $this->_orderRealId;

        // Close Date
        if ($order->getCreatedAt()) {
            // Always use order date as closing date if order already exists
            $this->_lead->CloseDate = date("Y-m-d", strtotime($order->getCreatedAt()));
        } else {
            // this should never happen
            $this->_lead->CloseDate = date("Y-m-d", time());
        }

        // Account ID
        $this->_lead->AccountId = $this->_customer->getSalesforceAccountId();
        // Description
        $this->_lead->Description = $this->_getDescriptionCart($order);

        ## Debug
        #$this->_lead->break = "Testing API failure";

        $this->_processMapping($order, "Opportunity");


        //Get Account Name from Salesforce
        $account = Mage::helper('tnw_salesforce/salesforce_data')->getAccountName($this->_customer->getSalesforceAccountId());
        $this->_fromAccount = ($account) ? $account : $this->_customer->getFirstname() . " " . $this->_customer->getLastname();

        $this->_setOpportunityName();

        unset($order);
    }

    protected function _setOpportunityName()
    {
        $this->_lead->Name = "Request #" . $this->_orderRealId . " from " . $this->_fromAccount;
    }

    protected function _getDescriptionCart($order)
    {
        ## Put Products into Single field
        $descriptionCart = "";
        $descriptionCart .= "Items ordered:\n";
        $descriptionCart .= "=======================================\n";
        $descriptionCart .= "SKU, Qty, Name";
        $descriptionCart .= ", Price";
        $descriptionCart .= ", Tax";
        $descriptionCart .= ", Subtotal";
        $descriptionCart .= ", Net Total";
        $descriptionCart .= "\n";
        $descriptionCart .= "=======================================\n";

        //foreach ($order->getAllItems() as $itemId=>$item) {
        foreach ($order->getAllVisibleItems() as $itemId => $item) {
            $descriptionCart .= $item->getSku() . ", " . number_format($item->getQtyOrdered()) . ", " . $item->getName();
            //Price
            $unitPrice = number_format(($item->getPrice()), 2, ".", "");
            $descriptionCart .= ", " . $unitPrice;
            //Tax
            $tax = number_format(($item->getTaxAmount()), 2, ".", "");
            $descriptionCart .= ", " . $tax;
            //Subtotal
            $subtotal = number_format((($item->getPrice() + $item->getTaxAmount()) * $item->getQtyOrdered()), 2, ".", "");
            $descriptionCart .= ", " . $subtotal;
            //Net Total
            $netTotal = number_format(($subtotal - $item->getDiscountAmount()), 2, ".", "");
            $descriptionCart .= ", " . $netTotal;
            $descriptionCart .= "\n";
        }
        $descriptionCart .= "=======================================\n";
        $descriptionCart .= "Sub Total: " . number_format(($order->getSubtotal()), 2, ".", "") . "\n";
        $descriptionCart .= "Tax: " . number_format(($order->getTaxAmount()), 2, ".", "") . "\n";
        $descriptionCart .= "Shipping (" . $order->getShippingDescription() . "): " . number_format(($order->getShippingAmount()), 2, ".", "") . "\n";
        $descriptionCart .= "Discount Amount : " . number_format($order->getGrandTotal() - ($order->getShippingAmount() + $order->getTaxAmount() + $order->getSubtotal()), 2, ".", "") . "\n";
        $descriptionCart .= "Total: " . number_format(($order->getGrandTotal()), 2, ".", "");
        $descriptionCart .= "\n";
        unset($order);
        return $descriptionCart;
    }

    /**
     * @param $order
     * @param $so
     */
    protected function _processMapping($order, $so)
    {
        $collection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter($so);

        foreach ($collection as $_map) {
            $_doSkip = $value = false;
            $conf = explode(" : ", $_map->local_field);
            $sf_field = $_map->sf_field;
            $attributeName = str_replace(" ", "", str_replace("_", " ", $conf[1])); //Full attribute name from Magento
            #Mage::helper('tnw_salesforce')->log("Processing: ".$_map->local_field);
            switch ($conf[0]) {
                case "Customer":
                    $attrName = str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                    if ($attrName == "Email") {
                        $email = $order->getCustomerEmail();
                        if (!$email) {
                            //TODO: add email
                            $email = $this->_customer->getEmail();
                        }
                        $value = $email;
                    } else {
                        #Mage::helper('tnw_salesforce')->log("Magento Attribute: ".$attributeName);
                        $attr = "get" . $attrName;

                        if ($this->_customer->getAttribute($conf[1])->getFrontendInput() == "select") {
                            $newAttribute = $this->_customer->getResource()->getAttribute($conf[1])->getSource()->getOptionText($this->_customer->$attr());
                        } else {
                            $newAttribute = $this->_customer->$attr();
                        }

                        // Reformat date fields
                        if ($_map->getBackendType() == "datetime" || $conf[1] == 'created_at') {
                            if ($this->_customer->$attr()) {
                                $timestamp = strtotime($this->_customer->$attr());
                                if ($conf[1] == 'created_at') {
                                    $newAttribute = gmdate(DATE_ATOM, $timestamp);
                                } else {
                                    $newAttribute = date("Y-m-d", $timestamp);
                                }
                            } else {
                                $_doSkip = true; //Skip this filed if empty
                            }
                        }
                        if (!$_doSkip) {
                            $value = $newAttribute;
                        }
                        unset($attributeInfo);
                    }
                    break;
                case "Billing":
                case "Shipping":
                    $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                    $var = 'get' . $conf[0] . 'Address';
                    $value = $order->$var()->$attr();
                    if (is_array($value)) {
                        $value = implode(", ", $value);
                    } else {
                        $value = ($value && !empty($value)) ? $value : NULL;
                    }
                    break;
                case "Custom":
                    if ($conf[1] == "current_url") {
                        $value = Mage::helper('core/url')->getCurrentUrl();
                    } elseif ($conf[1] == "todays_date") {
                        $value = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($conf[1] == "todays_timestamp") {
                        $value = gmdate(DATE_ATOM, strtotime(Mage::getModel('core/date')->timestamp(time())));
                    } elseif ($conf[1] == "end_of_month") {
                        $lastday = mktime(0, 0, 0, date("n") + 1, 0, date("Y"));
                        $value = date("Y-m-d", $lastday);
                    } elseif ($conf[1] == "store_view_name") {
                        $value = Mage::app()->getStore()->getName();
                    } elseif ($conf[1] == "store_group_name") {
                        $value = Mage::app()->getStore()->getGroup()->getName();
                    } elseif ($conf[1] == "website_name") {
                        $value = Mage::app()->getWebsite()->getName();
                    } else {
                        $value = $_map->default_value;
                        if ($value == "{{url}}") {
                            $value = Mage::helper('core/url')->getCurrentUrl();
                        } elseif ($value == "{{today}}") {
                            $value = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                        } elseif ($value == "{{end of month}}") {
                            $lastday = mktime(0, 0, 0, date("n") + 1, 0, date("Y"));
                            $value = date("Y-m-d", $lastday);
                        } elseif ($value == "{{contact id}}") {
                            $value = $this->_contactId;
                        } elseif ($value == "{{store view name}}") {
                            $value = Mage::app()->getStore()->getName();
                        } elseif ($value == "{{store group name}}") {
                            $value = Mage::app()->getStore()->getGroup()->getName();
                        } elseif ($value == "{{website name}}") {
                            $value = Mage::app()->getWebsite()->getName();
                        }
                    }
                    break;
                case "Order":
                    if ($conf[1] == "cart_all") {
                        $value = $this->_getDescriptionCart($order);
                    } elseif ($conf[1] == "number") {
                        $value = $this->_orderRealId;
                    } elseif ($conf[1] == "created_at") {
                        $value = ($order->getCreatedAt()) ? $order->getCreatedAt() : date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
                    } elseif ($conf[1] == "payment_method") {
                        $paymentMethods = Mage::helper('payment')->getPaymentMethodList(true);
                        $method = $order->getPayment()->getMethod();
                        if (array_key_exists($method, $paymentMethods)) {
                            $value = $paymentMethods[$order->getPayment()->getMethod()];
                        } else {
                            $value = $method;
                        }
                    } elseif ($conf[1] == "notes") {
                        $allNotes = NULL;
                        foreach ($order->getStatusHistoryCollection() as $_comment) {
                            $comment = trim(strip_tags($_comment->getComment()));
                            if (!$comment || empty($comment)) {
                                continue;
                            }
                            if (!$allNotes) {
                                $allNotes = "";
                            }
                            $allNotes .= Mage::helper('core')->formatTime($_comment->getCreatedAtDate(), 'medium') . " | " . $_comment->getStatusLabel() . "\n";
                            $allNotes .= strip_tags($_comment->getComment()) . "\n";
                            $allNotes .= "-----------------------------------------\n\n";
                        }
                        $value = $allNotes;
                    } else {
                        //Common attributes
                        $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                        $value = ($order->getAttributeText($conf[1])) ? $order->getAttributeText($conf[1]) : $order->$attr();
                        break;
                    }
                    break;
                case "Aitoc":
                    $oAitcheckoutfields = @Mage::getModel('aitcheckoutfields/aitcheckoutfields');
                    if ($oAitcheckoutfields) {
                        $aCustomAtrrList = $oAitcheckoutfields->getOrderCustomData($order->getId(), Mage::app()->getStore()->getId(), false);
                        $value = NULL;
                        foreach ($aCustomAtrrList as $_key => $_data) {
                            if ($_data['code'] == $conf[1]) {
                                $value = $_data['value'];
                                if ($_data['type'] == "date") {
                                    $value = date("Y-m-d", strtotime($value));
                                }
                                break;
                            }
                        }
                        unset($aCustomAtrrList);
                    }
                    unset($oAitcheckoutfields);
                    break;
                default:
                    break;
            }
            if ($value) {
                $this->_lead->$sf_field = $value;
            }
            Mage::helper('tnw_salesforce')->log($so . " Object: " . $sf_field . " = '" . $this->_lead->$sf_field . "'");
        }
        unset($collection, $_map, $order, $so);
    }

    protected function _customerRolePush()
    {
        // Role
        if (Mage::helper("tnw_salesforce")->isEnabledCustomerRole()) {
            // Assign Cotact Role
            Mage::helper('tnw_salesforce')->log("Updating Contact Role...");
            Mage::helper('tnw_salesforce/order_roles')->assignRole($this->_sfOrderId, $this->_customer->getSalesforceId());
        } else {
            Mage::helper('tnw_salesforce')->log("Opportunity Customer Role is disabled in config, skipping ...");
        }
    }

    /**
     * @param $order
     * @param $salesforceId
     */
    protected function _cartPush($order, $salesforceId)
    {
        // Cart
        if (
            Mage::helper("tnw_salesforce")->isEnabledProductSync() &&
            Mage::helper("tnw_salesforce")->isEnabledProductSync()
        ) {
            Mage::helper('tnw_salesforce')->log("Build a cart in Opportunity");

            /* Add new cart, nothing was shipped yet */
            Mage::helper('tnw_salesforce/order_pricebook')->buildCart(
                $order->getId(),
                $order->getAllVisibleItems(),
                $salesforceId
            );

            if ($this->_syncShipments) {
                $shipmentCollection = Mage::getResourceModel('sales/order_shipment_collection')
                    ->setOrderFilter($order->getId())
                    ->load();
                if (!empty($shipmentCollection)) {
                    //TODO: Need to actually scan through order Shipments and cart items
                    foreach ($shipmentCollection as $shipment) {
                        Mage::helper('tnw_salesforce')->log("###################################### Shipping Start ######################################");
                        Mage::helper('tnw_salesforce')->log("----- Shipping itmes from Order #" . $order->getRealOrderId() . " : " . $salesforceId . "-----");
                        Mage::helper('tnw_salesforce/shipment')->salesforcePush($shipment, $salesforceId);
                        Mage::helper('tnw_salesforce')->log("###################################### Shipping End ########################################");
                    }
                } else {
                    Mage::helper('tnw_salesforce')->log("Shipments collection is empty");
                }
            } else {
                Mage::helper('tnw_salesforce')->log("Manual Shipments Sync is disabled");
            }
        } else {
            Mage::helper('tnw_salesforce')->log("Product Synchronization is disabled in config, skipping ...");
        }
    }

    /**
     * @param $order
     */
    protected function _updateOrderStageName($order)
    {
        ## Status integration
        ## implemented in v.1.14
        $collection = Mage::getModel('tnw_salesforce/order_status')->getCollection();
        $collection->getSelect()
            ->where("main_table.status = ?", $order->getStatus());
        Mage::helper('tnw_salesforce')->log("Mapping status: " . $order->getStatus());

        foreach ($collection as $_item) {
            $this->_lead->StageName = ($_item->getSfOpportunityStatusCode()) ? $_item->getSfOpportunityStatusCode() : 'Committed';

            Mage::helper('tnw_salesforce')->log("Order status: " . $this->_lead->StageName);
            break;
        }
        unset($collection, $_item);
    }
}