<?php

class TNW_Salesforce_Helper_Order_Pricebook extends TNW_Salesforce_Helper_Order
{
    /* Salesforce ID for default Pricebook set in Magento */
    protected $_defaultPriceBook = NULL;

    /* Salesforce ID for default Pricebook set in Salesforce */
    protected $_standardPricebookId = NULL;

    /* Reference to Salesforce connection */
    protected $_mySforceConnection = NULL;

    /* Opportunity Line Item object */
    protected $_oli = NULL;
    protected $_shippedItem = NULL;

    /* Product2 object */
    protected $_p = NULL;
    protected $_magentoCartItems = array();

    /* Products to push */
    protected $_products = array();
    protected $_attributes = NULL;

    /* Order ID */
    protected $_orderId = NULL;

    /* Opportunity ID */
    protected $_opportunityId = NULL;

    /* Writer */
    protected $_write = NULL;

    /**
     * Test for product integration flag,
     * Try to extract the Salesforce connection from the helper, if not available
     * we instantiate another Salesforce connection
     */
    public function checkConnection()
    {
        if (!Mage::helper('tnw_salesforce')->isEnabledProductSync()) {
            Mage::helper('tnw_salesforce')->log("Product Integration is disabled");
            return false;
        }
        if (!$this->_mySforceConnection) {
            $this->_mySforceConnection = Mage::helper('tnw_salesforce/salesforce_data')->getClient();
        }
        $this->_standardPricebookId = Mage::helper('tnw_salesforce/salesforce_data')->getStandardPricebookId();
        $this->_defaultPriceBook = (Mage::helper('tnw_salesforce')->getDefaultPricebook()) ? Mage::helper('tnw_salesforce')->getDefaultPricebook() : $this->_standardPricebookId;

        $resource = Mage::getResourceModel('eav/entity_attribute');

        if (!$this->_attributes) {
            $this->_attributes['salesforce_id'] = $resource->getIdByCode('catalog_product', 'salesforce_id');
            $this->_attributes['salesforce_pricebook_id'] = $resource->getIdByCode('catalog_product', 'salesforce_pricebook_id');
        }
        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }
    }

    /**
     * @param null $opportunityId
     * @param null $cartItems
     * @return array
     *
     * Prepare Cart Items
     */
    protected function _prepareCartItems($opportunityId = NULL, $cartItems = NULL)
    {
        ## Reset products to push
        $this->_products = array();

        foreach ($cartItems as $itemId => $item) {
            $this->_oli = new stdClass();
            // Loading By SKU, because cart items are using configurable product IDs
            $product = Mage::helper('catalog/product')->getProduct($item->getSku(), Mage::app()->getStore()->getId(), 'sku');
            //$product = Mage::getModel('catalog/product')->load($item->getProductId());

            /* Sync product with Salesforce */
            $pricebookEntryId = $this->syncProduct($product, true); // True - Skip product sync during order

            if ($pricebookEntryId) {
                Mage::helper('tnw_salesforce')->log("Upserting product(" . $item->getProductId() . ") to opportunity (" . $opportunityId . ") with PricebookEntry ID: " . $pricebookEntryId);

                /* Load mapping for OpportunityLineItem */
                $collection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('OpportunityLineItem');
                foreach ($collection as $_map) {
                    $this->_processMapping($product, $_map, "_oli", $item);
                }
                unset($collection, $_map);
                $this->_oli->OpportunityId = $opportunityId;
                //$subtotal = number_format((($item->getPrice() * $item->getQtyOrdered()) + $item->getTaxAmount()), 2, ".", "");
                $subtotal = number_format(($item->getPrice() * $item->getQtyOrdered()), 2, ".", "");
                $netTotal = number_format(($subtotal - $item->getDiscountAmount()), 2, ".", "");
                $this->_oli->UnitPrice = $netTotal / $item->getQtyOrdered();
                $this->_oli->PricebookEntryId = $pricebookEntryId;
                $defaultServiceDate = Mage::helper('tnw_salesforce/shipment')->getDefaultServiceDate();
                if ($defaultServiceDate) {
                    $this->_oli->ServiceDate = $defaultServiceDate;
                }
                $opt = array();
                $options = $item->getProductOptions();
                if (is_array($options) && array_key_exists('options', $options)) {
                    foreach ($options['options'] as $_option) {
                        $opt[] = $_option['print_value'];
                    }
                }
                $this->_oli->Description = join(", ", $opt);
                $this->_oli->Quantity = $item->getQtyOrdered();

                /* Dump OpportunityLineItem object into the log */
                foreach ($this->_oli as $key => $_item) {
                    Mage::helper('tnw_salesforce')->log("OpportunityLineItem Object: " . $key . " = '" . $_item . "'");
                }
                unset($key);
                $this->_products[] = $this->_oli;
            } else {
                Mage::helper('tnw_salesforce')->log("Error: Product (" . $item->getProductId() . ") sync w/ SalesForce failed, PricebookEntry Id is unavailable!");
            }
            $this->_oli = NULL;
        }
        unset($shippedItems, $opportunityId, $cartItems, $itemId, $item, $existingCart, $pricebookEntryId);
        Mage::helper('tnw_salesforce')->log("Attaching updated cart to Opportunity, total " . count($this->_products) . " items...");
        return $this->_products;
    }

    /**
     * Adding products to the Opportunity as OpportunityLineItem(s)
     *
     * @param array $cartItems - contains array products in the order from Magento
     * @param string $opportunityId - contains the Opportunity ID
     */
    public function buildCart($orderId = NULL, $cartItems = NULL, $opportunityId = NULL)
    {
        $this->checkConnection();

        if (!$this->_mySforceConnection) {
            Mage::helper('tnw_salesforce')->log("SKIPPING: Salesforce connection failed!");
            return;
        }
        if (!$orderId) {
            Mage::helper('tnw_salesforce')->log("Order ID is missing, could not push the cart into Salesforce!");
            return false;
        }
        if (!$cartItems) {
            Mage::helper('tnw_salesforce')->log("No items in the cart!");
            return false;
        }
        if (!$opportunityId) {
            Mage::helper('tnw_salesforce')->log("Opportunity ID is not available");
            return false;
        }
        //Set order ID
        $this->_orderId = $orderId;
        $this->_opportunityId = $opportunityId;

        if (!$this->_defaultPriceBook) {
            if (!$this->_standardPricebookId) {
                Mage::helper('tnw_salesforce')->log("Default Pricebook is not set in Magento config, also Standard Pricebook is not configured in Salesforce");
                return false;
            } else {
                Mage::helper('tnw_salesforce')->log("Default Pricebook is not set in Magento config, using Standard Pricebook from Salesforce");
                $this->_defaultPriceBook = $this->_standardPricebookId;
            }
        }

        Mage::helper('tnw_salesforce')->log("------------------- OpportunityLineItem Sync Start -------------------");
        if (!$opportunityId && !$cartItems) {
            Mage::helper('tnw_salesforce')->log("Opportunity or cartItems are not defined, SKIPPING preparation!");
            return array();
        }

        $products = $this->_prepareCartItems($opportunityId, $cartItems);
        if (count($products) == 0) {
            return;
        }

        if (Mage::helper('tnw_salesforce')->getApiType() == "Partner") {
            $prodObject = array();
            foreach ($products as $_prod) {
                $sObject = new SObject();
                $sObject->fields = (array)$_prod;
                $sObject->type = 'OpportunityLineItem';
                $prodObject[] = $sObject;
            }
            unset($sObject, $_prod);
            Mage::dispatchEvent("tnw_salesforce_opportunitylineitem_send_before",array("data" => $prodObject));
            $response = $this->_mySforceConnection->upsert('Id', $prodObject);
            Mage::dispatchEvent("tnw_salesforce_opportunitylineitem_send_after",array(
                "data" => $prodObject,
                "result" => $response
            ));
            unset($prodObject);
        } else {
            Mage::dispatchEvent("tnw_salesforce_opportunitylineitem_send_before",array("data" => $products));
            $response = $this->_mySforceConnection->upsert('Id', $products, 'OpportunityLineItem');
            Mage::dispatchEvent("tnw_salesforce_opportunitylineitem_send_after",array(
                "data" => $products,
                "result" => $response
            ));
        }

        $reAddedProducts = array();
        $cartUpdateFlag = true;
        foreach ($response as $_responseRow) {
            if (!$_responseRow->success) {
                $cartUpdateFlag = false;
                if (is_array($_responseRow->errors)) {
                    foreach ($_responseRow->errors as $_error) {
                        Mage::helper('tnw_salesforce')->log("Error: " . $_error->message);
                    }
                } else {
                    Mage::helper('tnw_salesforce')->log($_responseRow->errors->message);
                }
            } else {
                $reAddedProducts[] = $_responseRow->id;

            }
        }
        unset($response, $_responseRow, $_error);
        if (!$cartUpdateFlag) {
            Mage::helper('tnw_salesforce')->log("Failed to upsert some products, see the errors above.");
        } else {
            Mage::helper('tnw_salesforce')->log("All products upserted into the Opportunity successfully. Product Id(s): " . join(", ", $reAddedProducts));
            $this->orderAfterPush();
        }
        unset($reAddedProducts, $cartUpdateFlag);
        Mage::helper('tnw_salesforce')->log("------------------- OpportunityLineItem Sync End -------------------");
    }

    /**
     * syncronize magento product with salesforce
     *
     * @param null $product
     * @param bool $_isOrder
     * @return bool
     */
    public function syncProduct($product = NULL, $_isOrder = false)
    {
        if (
            !Mage::helper('tnw_salesforce')->isWorking()
            || Mage::getSingleton('core/session')->getFromSalesForce()
        ) {
            return false;
        }
        if (!$product) {
            Mage::helper('tnw_salesforce')->log("Product Syncronization failed, expected Magneto product object is not available.");
            return false;
        }
        $this->checkConnection();
        if (!$this->_mySforceConnection) {
            Mage::helper('tnw_salesforce')->log("SKIPPING: Salesforce connection failed!");
            return;
        }
        /* Sync */
        $_magentoProdId = $product->getId();
        $pricebookEntryId = $product->getSalesforcePricebookId();
        $_sfProductId = $product->getSalesforceId();
        $productPrice = number_format($product->getPrice(), 2, ".", "");
        $_doPricebookUpdate = false;

        // Figure out what needs to happen next
        if ($_isOrder) {
            if (!$pricebookEntryId) {
                $_doPricebookUpdate = true;
            }
        } else {
            $_doPricebookUpdate = true;
        }

        Mage::helper('tnw_salesforce')->log("------------------- Product2 Sync Start -------------------");
        Mage::helper('tnw_salesforce')->log("Magento Product: " . $_magentoProdId);
        Mage::helper('tnw_salesforce')->log("Product: " . $_sfProductId);
        Mage::helper('tnw_salesforce')->log("PricebookEntryId: " . $pricebookEntryId);
        // Try to find the product in Salesforce by SKU
        $sfProduct = Mage::helper('tnw_salesforce/salesforce_data')->productLookup($product->getSku());
        $_sfProductId = (is_array($sfProduct) && array_key_exists($product->getSku(), $sfProduct)) ? $sfProduct[$product->getSku()]->Id : NULL;
        Mage::helper('tnw_salesforce')->log("SF Lookup Product ID: " . $_sfProductId);

        Mage::helper('tnw_salesforce')->log("Upserting Product # " . $_magentoProdId . " - SF Id: " . $_sfProductId);
        unset($sfProduct);
        if (!$_sfProductId) {
            Mage::helper('tnw_salesforce')->log("Product not found in SF, creating ...");
            $responseP = $this->upsertProduct($product, $productPrice, $_sfProductId);
            if (!$responseP->success) {
                Mage::helper('tnw_salesforce')->log("Failed to upsert product: " . $_magentoProdId);
                Mage::helper('tnw_salesforce')->log("------------------- Product2 Sync End -------------------");
                unset($responseP, $_magentoProdId, $productPrice, $_sfProductId);
                return false;
            } else {
                if ($_sfProductId && $_sfProductId != $responseP->id) {
                    /* This should never happen */
                    Mage::helper('tnw_salesforce')->log("Product2 duplicated! Old Id: " . $_sfProductId . "  - New Id: " . $responseP->id);
                }
                $_sfProductId = $responseP->id;
                $product->setSalesforceId($_sfProductId);
                unset($responseP);

                if ($_doPricebookUpdate) {
                    if (!$this->_standardPricebookId != $this->_defaultPriceBook) {
                        $sfPricebookEntryId = Mage::helper('tnw_salesforce/salesforce_data')->pricebookEntryLookup($_sfProductId, $this->_standardPricebookId);
                        if (!$sfPricebookEntryId) {
                            $responsePB = $this->upsertPricebookEntry($_sfProductId, $productPrice, $this->_standardPricebookId);
                            if (!$responsePB[0]->success) {
                                Mage::helper('tnw_salesforce')->log("Failed to create Standard PricebookEntry for product: " . $_magentoProdId);
                                unset($_magentoProdId, $productPrice, $_sfProductId, $responsePB);
                                return false;
                            } else {
                                Mage::helper('tnw_salesforce')->log("Standard PricebookEntry Id is not found in Magento...");
                                Mage::helper('tnw_salesforce')->log("Standard StandardEntry Id (#" . $responsePB[0]->id . ") created...");
                                $product->setSalesforcePricebookId($responsePB[0]->id);
                            }
                        } else {
                            $product->setSalesforcePricebookId($sfPricebookEntryId);
                            Mage::helper('tnw_salesforce')->log("Standard PricebookEntry Id (#" . $sfPricebookEntryId . ") found in Salesforce and Magneto");
                        }
                    }
                    $sfPricebookEntryId = Mage::helper('tnw_salesforce/salesforce_data')->pricebookEntryLookup($_sfProductId, $this->_defaultPriceBook);
                    if (!$sfPricebookEntryId) {
                        $responsePB = $this->upsertPricebookEntry($_sfProductId, $productPrice, $this->_defaultPriceBook);
                        if (!$responsePB[0]->success) {
                            Mage::helper('tnw_salesforce')->log("Failed to create Default PricebookEntry for product: " . $_magentoProdId);
                            unset($_magentoProdId, $productPrice, $_sfProductId, $responsePB);
                            return false;
                        } else {
                            Mage::helper('tnw_salesforce')->log("Default PricebookEntry Id is not found in Magento...");
                            Mage::helper('tnw_salesforce')->log("Default PricebookEntry Id (#" . $responsePB[0]->id . ") created...");
                            $product->setSalesforcePricebookId($responsePB[0]->id);
                        }
                    } else {
                        $product->setSalesforcePricebookId($sfPricebookEntryId);
                        Mage::helper('tnw_salesforce')->log("Default PricebookEntry Id (#" . $sfPricebookEntryId . ") found in Salesforce and Magneto");
                    }
                    $sfPricebookEntryId = $product->getSalesforcePricebookId();;
                } else {
                    $sfPricebookEntryId = $pricebookEntryId;
                }

                Mage::helper('tnw_salesforce')->log("------------------- Product2 Sync End -------------------");
            }
        } else {
            Mage::helper('tnw_salesforce')->log("Product found in SF (Id: " . $_sfProductId . ") updating values.");
            if (!$pricebookEntryId) {
                $sfPricebookEntryId = Mage::helper('tnw_salesforce/salesforce_data')->pricebookEntryLookup($_sfProductId, $this->_defaultPriceBook);
            } else {
                $sfPricebookEntryId = $pricebookEntryId;
            }

            Mage::helper('tnw_salesforce')->log("Product not found in SF, creating ...");
            $responseP = $this->upsertProduct($product, $productPrice, $_sfProductId);
            if (!$responseP->success) {
                Mage::helper('tnw_salesforce')->log("Failed to upsert product: " . $_magentoProdId);
                Mage::helper('tnw_salesforce')->log("------------------- Product2 Sync End -------------------");
                unset($responseP, $_magentoProdId, $productPrice, $_sfProductId);
                return false;
            } else {
                Mage::helper('tnw_salesforce')->log("Product upserted: " . $_sfProductId);
            }
            /* Update the values */
            $product->setSalesforcePricebookId($sfPricebookEntryId);
            $product->setSalesforceId($_sfProductId);

            Mage::helper('tnw_salesforce')->log("------------------- Product2 Sync End -------------------");
        }

        $sql = "";
        $row = $this->_write->query("SELECT value_id FROM `" . Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_varchar') . "` WHERE store_id = '" . Mage::helper('tnw_salesforce')->getStoreId() . "' AND attribute_id = '" . $this->_attributes['salesforce_id'] . "' AND entity_id = '" . $product->getId() . "'")->fetch();
        $vid = ($row) ? $row['value_id'] : NULL;
        if ($vid) {
            $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_varchar') . "` SET value = '" . $product->getSalesforceId() . "' WHERE value_id = '" . $vid . "';";
        } else {
            $sql .= "INSERT INTO `" . Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_varchar') . "` VALUES (NULL,4," . $this->_attributes['salesforce_id'] . "," . Mage::helper('tnw_salesforce')->getStoreId() . "," . $product->getId() . ",'" . $product->getSalesforceId() . "');";
        }
        $vid = NULL;
        $row = $this->_write->query("SELECT value_id FROM `" . Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_varchar') . "` WHERE store_id = '" . Mage::helper('tnw_salesforce')->getStoreId() . "' AND attribute_id = '" . $this->_attributes['salesforce_pricebook_id'] . "' AND entity_id = '" . $product->getId() . "'")->fetch();
        $vid = ($row) ? $row['value_id'] : NULL;
        if ($vid) {
            $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_varchar') . "` SET value = '" . $product->getSalesforcePricebookId() . "' WHERE value_id = '" . $vid . "';";
        } else {
            $sql .= "INSERT INTO `" . Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_varchar') . "` VALUES (NULL,4," . $this->_attributes['salesforce_pricebook_id'] . "," . Mage::helper('tnw_salesforce')->getStoreId() . "," . $product->getId() . ",'" . $product->getSalesforcePricebookId() . "');";
        }
        Mage::helper("tnw_salesforce")->log($sql);
        $this->_write->query($sql);

        return $sfPricebookEntryId;
    }

    /**
     * Upserting PriceBookEntry
     *
     * @param string $prodId
     * @param float $price
     * @param string $defaultPB
     * @param string $pbeId
     * @return boolean|object
     */
    protected function upsertPricebookEntry($prodId = NULL, $price = NULL, $defaultPB = NULL, $pbeId = NULL)
    {
        if (!$prodId || !$price || !$defaultPB) {
            return false;
        }
        try {
            $pb = new stdClass();
            if (!$pbeId) {
                $pb->Pricebook2Id = $defaultPB;
                $pb->Product2Id = $prodId;
            }
            $pb->Id = ($pbeId) ? $pbeId : NULL;
            $pb->UseStandardPrice = FALSE;
            $pb->UnitPrice = $price;
            $pb->isActive = TRUE;

            if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
                //$syncParam = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix()."disableMagentoSync__c";
                //$pb->$syncParam = true;
            }

            unset($prodId, $price, $defaultPB, $pbeId);

            foreach ($pb as $key => $value) {
                Mage::helper('tnw_salesforce')->log("PricebookEntry Object: " . $key . " = '" . $value . "'");
            }

            if (Mage::helper('tnw_salesforce')->getApiType() == "Partner") {
                $sObject = new SObject();
                $sObject->fields = (array)$pb;
                $sObject->type = 'PricebookEntry';
                Mage::dispatchEvent("tnw_salesforce_pricebookentry_send_before",array("data" => array($sObject)));
                $upsertResponse = $this->_mySforceConnection->upsert('Id', array($sObject));
                Mage::dispatchEvent("tnw_salesforce_pricebookentry_send_after",array(
                    "data" => array($sObject),
                    "result" => $upsertResponse
                ));
                unset($sObject);
            } else {
                Mage::dispatchEvent("tnw_salesforce_pricebookentry_send_before",array("data" => array($pb)));
                $upsertResponse = $this->_mySforceConnection->upsert('Id', array($pb), 'PricebookEntry');
                Mage::dispatchEvent("tnw_salesforce_pricebookentry_send_after",array(
                    "data" => array($pb),
                    "result" => $upsertResponse
                ));
            }
            unset($pb, $key, $value);
            if ($upsertResponse[0]->success) {
                Mage::helper('tnw_salesforce')->log("PricebookEntry upsert successful: " . $upsertResponse[0]->id);
            } else {
                if (is_array($upsertResponse[0]->errors)) {
                    Mage::helper('tnw_salesforce')->log("PricebookEntry upsert failed: ");
                    foreach ($upsertResponse[0]->errors as $_error) {
                        Mage::helper('tnw_salesforce')->log("Error: " . $_error->message);
                    }
                    unset($_error);
                } else {
                    Mage::helper('tnw_salesforce')->log("Error: " . $upsertResponse[0]->errors->message);
                }
            }

            return $upsertResponse;

        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Error: " . $e->faultstring);
            Mage::helper('tnw_salesforce')->log("Could not upset pricebook entry");
            return false;
        }
    }

    /**
     * Upserting product into Salesforce
     *
     * @param object $prod
     * @param float $price
     * @param string $pId
     */
    protected function upsertProduct($prod = NULL, $price = NULL, $pId = NULL)
    {
        if (!$prod) {
            Mage::helper('tnw_salesforce')->log("Product2 cannot be created because Magento product object is not avaialble.");
            return false;
        }
        if (!$price) {
            Mage::helper('tnw_salesforce')->log("Product2 cannot be created because Magento product price is not set.");
            return false;
        }
        if (!$prod->getId()) {
            Mage::helper('tnw_salesforce')->log("Product is not created in Magento yet, SKIPPING!");
            return false;
        }
        try {
            //$this->_defaultPriceBook = ($this->_defaultPriceBook) ? $this->_defaultPriceBook : Mage::helper('tnw_salesforce')->getDefaultPricebook();
            //$this->_standardPricebookId = ($this->_standardPricebookId) ? $this->_standardPricebookId : Mage::helper('tnw_salesforce/salesforce_data')->getStandardPricebookId();
            $syncParamId = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . "Magento_ID__c";

            $this->_p = new stdClass();
            //$this->_p->Id = ($pId) ? $pId : NULL;     //Upserting only on Magento ID

            // Defaults
            $this->setDefaultProductValues($prod);
            $this->_p->isActive = TRUE;
            if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
                $syncParam = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix() . "disableMagentoSync__c";
                $this->_p->$syncParam = TRUE;
            }

            //Process mapping
            $collection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('Product2');
            foreach ($collection as $_map) {
                $this->_processMapping($prod, $_map);
            }

            $this->_p->ProductCode = $prod->getSku();
            $this->_p->$syncParamId = $prod->getId();

            foreach ($this->_p as $key => $value) {
                Mage::helper('tnw_salesforce')->log("Product Object: " . $key . " = '" . $value . "'");
            }

            unset($collection, $_map);

            if (Mage::helper('tnw_salesforce')->getApiType() == "Partner") {
                $sObject = new SObject();
                $sObject->fields = (array)$this->_p;
                $sObject->type = 'Product2';
                Mage::dispatchEvent("tnw_salesforce_product2_send_before",array("data" => array($sObject)));
                $upsertResponse = $this->_mySforceConnection->upsert($syncParamId, array($sObject));
                Mage::dispatchEvent("tnw_salesforce_product2_send_after",array(
                    "data" => array($sObject),
                    "result" => $upsertResponse
                ));
                unset($sObject);
            } else {
                Mage::dispatchEvent("tnw_salesforce_product2_send_before",array("data" => array($this->_p)));
                $upsertResponse = $this->_mySforceConnection->upsert($syncParamId, array($this->_p), 'Product2');
                Mage::dispatchEvent("tnw_salesforce_product2_send_after",array(
                    "data" => array($this->_p),
                    "result" => $upsertResponse
                ));
            }

            if (!$upsertResponse[0]->success) {
                if (is_array($upsertResponse[0]->errors)) {
                    Mage::helper('tnw_salesforce')->log("Failed to upsert product!");
                    foreach ($upsertResponse[0]->errors as $_error) {
                        Mage::helper('tnw_salesforce')->log("Error: " . $_error->message);
                    }
                } else {
                    Mage::helper('tnw_salesforce')->log("Failed to upsert product: " . $upsertResponse[0]->errors->message);
                }
                unset($upsertResponse, $_error);
                return false;
            } else {
                $upsertedProductId = $upsertResponse[0]->id;
                Mage::helper('tnw_salesforce')->log("Product2: " . $upsertedProductId . ", upserted successfully");
                /* Lookup if Standard Pricebook Entry exists */
                $sfPricebookEntry = Mage::helper('tnw_salesforce/salesforce_data')->pricebookEntryLookup($upsertedProductId, $this->_standardPricebookId);
                // Set Standard Pricebook Id
                $prod->setSalesforcePricebookId($sfPricebookEntry);
                if (!$sfPricebookEntry) {
                    Mage::helper('tnw_salesforce')->log("Standard PriebookEntry for this product doesn't exist, creating...");
                    $response = $this->upsertPricebookEntry($upsertedProductId, $price, $this->_standardPricebookId);
                    if (!$response[0]->success) {
                        if (is_array($response[0]->errors)) {
                            Mage::helper('tnw_salesforce')->log("Failed to upsert standard pricebook entity: ");
                            foreach ($response[0]->errors as $_error) {
                                Mage::helper('tnw_salesforce')->log("Error: " . $_error->message);
                            }
                        } else {
                            Mage::helper('tnw_salesforce')->log("Failed to upsert standard pricebook entity: " . $response[0]->errors->message);
                        }
                        return $response[0]; // Expected to have an error
                    } else {
                        $prod->setSalesforcePricebookId($response[0]->id);
                        Mage::helper('tnw_salesforce')->log("Standard PriebookEntry for this product created!");
                    }
                }
                unset($upsertedProductId, $sfPricebookEntry, $response);
                return $upsertResponse[0];
            }
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log($e->faultstring);
            return false;
        }
    }

    protected function setDefaultProductValues($prod)
    {
        $this->_p->Name = $prod->getName();
    }

    protected function orderAfterPush()
    {
    }

    /**
     * Gets field mapping from Magento and creates OpportunityLineItem object
     */
    protected function _processMapping($prod = NULL, $_map = NULL, $type = "_p", $cartItem = NULL)
    {
        $value = false;
        $conf = explode(" : ", $_map->local_field);
        $sf_field = $_map->sf_field;

        switch ($conf[0]) {
            case "Cart":
                if ($cartItem) {
                    if ($conf[1] == "total_product_price") {
                        $subtotal = number_format((($cartItem->getPrice() + $cartItem->getTaxAmount()) * $cartItem->getQtyOrdered()), 2, ".", "");
                        $value = number_format(($subtotal - $cartItem->getDiscountAmount()), 2, ".", "");
                    }
                }
                break;
            case "Product Inventory":
                $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod);
                $value = ($stock) ? (int)$stock->getQty() : NULL;
                break;
            case "Product":
                $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                $value = ($prod->getAttributeText($conf[1])) ? $prod->getAttributeText($conf[1]) : $prod->$attr();
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
            default:
                break;
        }
        if ($value) {
            $this->$type->$sf_field = $value;
        }
    }
}
