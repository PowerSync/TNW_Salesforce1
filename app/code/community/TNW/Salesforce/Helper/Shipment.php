<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Shipment extends TNW_Salesforce_Helper_Abstract
{
    const CARRIER_MATCHING = 'salesforce_shipment/shipment_configuration/carrier_matching';
    const SYNC_SHIPMENT_ENABLED = 'salesforce_shipment/shipment_configuration/sync_enabled';

    protected $_mySforceConnection = NULL;
    protected $_oli = NULL;
    protected $_shippedOLI = NULL;

    protected function checkConnection()
    {
        /* Initiate connection */
        try {
            $this->_mySforceConnection = Mage::helper('tnw_salesforce/salesforce_data')->getClient();
            if (!$this->_mySforceConnection) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Salesforce connection failed!");
                return;
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not get Salesforce connection");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR:" . $e->getMessage());
            return;
        }
    }

    public function salesforcePush($shipment = NULL, $opportunityId = NULL)
    {
        if (!$shipment || !$opportunityId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Shipment or Opportunity ID is missing");
            return;
        }
        $this->checkConnection();

        try {
            // Lookup Oppotunity in Salesforce and extract all cart items
            $existingCart = $this->_getExistingCartItems($opportunityId);
            $products = array();
            if (!$shipment->getCreatedAt()) {
                $shipment->setCreatedAt(date('Y-M-d H:m:s', Mage::getModel('core/date')->timestamp(time())));
            }
            $shimpmentDateTime = Mage::getModel('core/date')->timestamp(strtotime($shipment->getCreatedAt()));
            foreach ($shipment->getAllItems() as $_item) {
                $product = Mage::getModel('catalog/product')->load($_item->getProductId());

                /* Does shipped item already exist in Salesforce */
                $this->_oli = $this->_isShippable($existingCart, $product->getSalesforcePricebookId());
                if ($existingCart && $this->_oli) {
                    $priceBookEntryId = $this->_oli->PricebookEntryId;
                    unset($this->_oli->PricebookEntryId);
                    /* Check qty */
                    if ($_item->getQty() == $this->_oli->Quantity) {
                        // Shipping entire item
                        $this->_oli->ServiceDate = date("Y-m-d", $shimpmentDateTime);
                        $_item->setSalesforceId($this->_oli->Id);
                    } else {
                        $this->_shippedOLI = NULL; // reset
                        $_item->setSalesforceId($this->_oli->Id);

                        // Partial Shipment
                        $this->_shippedOLI = clone $this->_oli;
                        unset($this->_shippedOLI->Id);

                        $this->_shippedOLI->Quantity = $this->_oli->Quantity - $_item->getQty();
                        $this->_shippedOLI->PricebookEntryId = $priceBookEntryId;
                        $this->_shippedOLI->OpportunityId = $opportunityId;
                        if (!$this->getDefaultServiceDate()) {
                            unset($this->_shippedOLI->ServiceDate);
                        } else {
                            $this->_shippedOLI->ServiceDate = $this->getDefaultServiceDate();
                        }

                        // Custom Mapping
                        $collection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
                            ->addObjectToFilter('OpportunityLineItem')
                            ->firstSystem();
                        foreach ($collection as $_map) {
                            $this->_processMapping($product, $_map, "_shippedOLI");
                        }
                        unset($collection, $_map);

                        $this->_oli->Quantity = $_item->getQty();
                        $this->_oli->ServiceDate = date("Y-m-d", $shimpmentDateTime);
                    }
                    // Upsert Salesforce
                    $products[] = $this->_oli;
                    if ($this->_shippedOLI) {
                        $products[] = $this->_shippedOLI;
                    }
                    $this->_shippedOLI = NULL; //Reset
                    $this->_oli = NULL; // Reset
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: failed to locate shippable item in Salesforce for product SKU: " . $_item->getSku());
                }
            }

            Mage::dispatchEvent("tnw_salesforce_opportunity_line_item_send_before",array("data" => $products));
            $response = $this->getClient()->upsert('Id', $products, 'OpportunityLineItem');
            Mage::dispatchEvent("tnw_salesforce_opportunity_line_item_send_after",array(
                "data" => $products,
                "result" => $response
            ));

            foreach ($response as $_responseRow) {
                if (!$_responseRow->success) {
                    if (is_array($_responseRow->errors)) {
                        foreach ($_responseRow->errors as $_error) {
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $_error->message);
                        }
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($_responseRow->errors->message);
                    }
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("OpportunityLineItem ID: " . $_responseRow->id . " upserted successfully");
                }
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError($e->getMessage());
            if (!$e->getMessage()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Exception caught, but error is not returned!");
            }
        }
        return;
    }

    protected function _isShippable($cart, $pbeId)
    {
        $cartItem = NULL;
        foreach ($cart as $_item) {
            if (
                property_exists($_item, 'PricebookEntryId') &&
                $_item->PricebookEntryId == $pbeId &&
                (!$this->getDefaultServiceDate() || $_item->ServiceDate == $this->getDefaultServiceDate())
            ) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("returning cartitem");
                $cartItem = $_item;
                break;
            }
        }

        return $cartItem;
    }

    public function getDefaultServiceDate()
    {
        return NULL;
    }

    /**
     * @param null $opportunityId
     * @return mixed
     *
     * Call Salesforce and extract existing CartItems from an Opportunity
     */
    protected function _getExistingCartItems($opportunityId = NULL)
    {
        return Mage::helper('tnw_salesforce/salesforce_data')->getOpportunityItems($opportunityId);
    }

    /**
     * Gets field mapping from Magento and creates OpportunityLineItem object
     */
    protected function _processMapping($prod = NULL, $_map = NULL, $type = "_shippedOLI")
    {
        $conf = explode(" : ", $_map->local_field);
        $sf_field = $_map->sf_field;

        switch ($conf[0]) {
            case "Product Inventory":
                $stocklevel = (int)Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod)->getQty();
                $this->$type->$sf_field = $stocklevel;
                break;
            case "Product":
                $attr = "get" . str_replace(" ", "", ucwords(str_replace("_", " ", $conf[1])));
                $this->$type->$sf_field = ($prod->getAttributeText($conf[1])) ? $prod->getAttributeText($conf[1]) : $prod->$attr();
                break;
            case "Custom":
                $value = $_map->default_value;
                if ($value == "{{url}}") {
                    $value = Mage::helper('core/url')->getCurrentUrl();
                } elseif ($value == "{{today}}") {
                    $value = date("Y-m-d", now());
                } elseif ($value == "{{end of month}}") {
                    $lastday = mktime(0, 0, 0, date("n") + 1, 0, date("Y"));
                    $value = date("Y-m-d", $lastday);
                }
                $this->$type->$sf_field = $value;
                break;
            default:
                break;
        }
    }

    /**
     * @param string $carrier
     *
     * @return null|string
     */
    public function getAccountByCarrier($carrier)
    {
        $config = unserialize(Mage::getStoreConfig(self::CARRIER_MATCHING));
        if (empty($config)) {
            return null;
        }

        foreach ($config as $configRow) {
            if (isset($configRow['carrier']) && $configRow['carrier'] == $carrier) {
                return isset($configRow['account']) ? (string)$configRow['account'] : null;
            }
        }

        return null;
    }

    /**
     * @return bool
     */
    public function syncEnabled()
    {
        return Mage::getStoreConfigFlag(self::SYNC_SHIPMENT_ENABLED);
    }
}