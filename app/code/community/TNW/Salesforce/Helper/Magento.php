<?php

/**
 * Class TNW_Salesforce_Helper_Magento
 */
class TNW_Salesforce_Helper_Magento extends TNW_Salesforce_Helper_Abstract
{
    /**
     * @var array
     */
    protected $_magentoFields = array();

    /**
     * @var array
     */
    protected $_cache = array();

    /**
     * @var array
     */
    public $productInventoryFieldList = array(
        'manage_stock',
        'qty',
        'min_qty',
        'min_sale_qty',
        'max_sale_qty',
        'is_qty_decimal',
        'backorders',
        'notify_stock_qty',
        'enable_qty_increments',
        'is_in_stock',
    );

    protected $_acl = array();

    public function __construct()
    {
        $this->_populateAcl();
    }

    protected function _populateAcl() {
        $this->_acl['order'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_OBJECT,
            TNW_Salesforce_Model_Config_Objects::INVOICE_OBJECT
        );

        $this->_acl['abandoned'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::ABANDONED_OBJECT,
        );
        $this->_acl['abandonedItem'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::ABANDONED_ITEM_OBJECT,
        );

        $this->_acl['customer'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::LEAD_OBJECT,
            TNW_Salesforce_Model_Config_Objects::CONTACT_OBJECT,
            TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ABANDONED_OBJECT,
            TNW_Salesforce_Model_Config_Objects::INVOICE_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ACCOUNT_OBJECT
        );
        $this->_acl['cart'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_ITEM_OBJECT
        );
        $this->_acl['product'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ABANDONED_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::PRODUCT_OBJECT,
            TNW_Salesforce_Model_Config_Objects::INVOICE_ITEM_OBJECT
        );

        $this->_acl['invoice'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::INVOICE_OBJECT
        );
        $this->_acl['invoiceItem'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::INVOICE_ITEM_OBJECT,
        );
    }

    /**
     * @param null $type
     * @return array
     */
    public function getMagentoAttributes($type = NULL)
    {
        if (!$type) {
            Mage::helper('tnw_salesforce')->log("Magento fields drop down failed to create, missing type");
            return array();
        }
        if (!array_key_exists($type, $this->_cache) || empty($this->_cache[$type])) {
            $this->_buildDropDown($type);
        }
        return $this->_cache[$type];
    }

    /**
     * @param $type
     */
    protected function _buildDropDown($type)
    {
        /* Customization */
        $this->_customizationAttributesTop($type);

        if (in_array($type, $this->_acl['invoice'])) {
            $this->_populateInvoiceAttributes($type);
        }

        if (in_array($type, $this->_acl['order'])) {
            $this->_populateOrderAttributes($type);
            $this->_populatePaymentAttributes($type);
        }

        if (in_array($type, $this->_acl['abandoned'])) {
            $this->_populateAbandonedAttributes($type);
        }

        if (in_array($type, $this->_acl['customer'])) {
            /* Customer Attributes */
            $this->_populateCustomerAttributes($type);

            /* Add Customer Group Attributes */
            $this->_populateCustomerGroupAttributes($type);

            /* Customer Address Attributes */
            $this->_populateAddressAttributes("billing", $type);
            $this->_populateAddressAttributes("shipping", $type);

            /* Aitoc Custom Fields */
            $modules = Mage::getConfig()->getNode('modules')->children();
            if (property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
                $this->_populateAitocAttributes($type);
            }
        }

        if (in_array($type, $this->_acl['abandonedItem'])) {
            $this->_populateAbandonedItemAttributes($type);
        }

        if (in_array($type, $this->_acl['invoiceItem'])) {
            $this->_populateInvoiceItemAttributes($type);
        }

        if (in_array($type, $this->_acl['cart'])) {
            /* Cart Attributes */
            $this->_populateCartAttributes($type);
        }

        if (in_array($type, $this->_acl['product'])) {
            /* Product Attributes */
            $this->_populateProductAttributes($type);
        }

        /* Customization */
        $this->_customizationAttributesBottom($type);

        /* Customer Attributes */
        $this->_populateCustomAttributes($type);
    }

    protected function _customizationAttributesTop($type)
    {
        /* Leaving for customization */
    }

    protected function _customizationAttributesBottom($type)
    {
        /* Leaving for customization */
    }

    protected function _populateOrderAttributes($type)
    {
        try {
            $collection = $this->getTableColumnList('sales_flat_order');
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Could not load Magento order schema...");
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
        }

        if ($collection) {
            $this->_cache[$type]['order'] = array(
                'label' => 'Order',
                'value' => array()
            );

            $this->_cache[$type]['order']['value'] = array(
                array(
                    'value' => 'Order : number',
                    'label' => 'Order : Number',
                ),
                array(
                    'value' => 'Order : cart_all',
                    'label' => 'Order : All Cart Items',
                ),
                array(
                    'value' => 'Order : payment_method',
                    'label' => 'Order : Payment Method Label',
                ),
                array(
                    'value' => 'Order : notes',
                    'label' => 'Order : Status Notes (Admin)',
                )
            );

            foreach ($collection as $_attribute) {
                $key = $_attribute['Field'];
                if (
                    $key == 'increment_id'
                    || $key == 'sf_insync'
                    || $key == 'salesforce_id'
                    || $key == 'status'
                ) {
                    continue;
                }

                $this->_cache[$type]['order']['value'][] = array(
                    'value' => 'Order : ' . $key,
                    'label' => 'Order : ' . ucwords(str_replace("_", " ", $key)),
                );
            }
        }
    }

    protected function _populateInvoiceAttributes($type)
    {
        try {
            $collection = $this->getTableColumnList('sales_flat_invoice');
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Could not load Magento order schema...");
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
        }

        if ($collection) {
            $this->_cache[$type]['invoice'] = array(
                'label' => 'Invoice',
                'value' => array()
            );

            foreach ($collection as $_attribute) {
                $key = $_attribute['Field'];
                if (
                    $key == 'entity_id'
                    || $key == 'sf_insync'
                    || $key == 'salesforce_id'
                    || $key == 'can_void_flag'
                    || $key == 'shipping_address_id'
                    || $key == 'billing_address_id'
                    || $key == 'increment_id'
                    || $key == 'transaction_id'
                ) {
                    continue;
                }

                $this->_cache[$type]['invoice']['value'][] = array(
                    'value' => 'Invoice : ' . $key,
                    'label' => 'Invoice : ' . ucwords(str_replace("_", " ", $key)),
                );
            }
        }
    }

    protected function _populateAbandonedAttributes($type)
    {
        try {
            $collection = $this->getTableColumnList('sales_flat_quote');
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Could not load Magento order schema...");
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
        }

        if ($collection) {
            $this->_cache[$type]['abandoned'] = array(
                'label' => 'Abandoned',
                'value' => array()
            );

            $this->_cache[$type]['abandoned']['value'] = array(
                array(
                    'value' => 'Cart : number',
                    'label' => 'Cart : Number',
                ),
                array(
                    'value' => 'Cart : cart_all',
                    'label' => 'Cart : All Cart Items',
                ),
            );

            foreach ($collection as $_attribute) {
                $key = $_attribute['Field'];
                if (
                    $key == 'increment_id'
                    || $key == 'sf_insync'
                    || $key == 'salesforce_id'
                    || $key == 'status'
                ) {
                    continue;
                }

                $this->_cache[$type]['abandoned']['value'][] = array(
                    'value' => 'Cart : ' . $key,
                    'label' => 'Cart : ' . ucwords(str_replace("_", " ", $key)),
                );
            }
        }
    }

    protected function _populatePaymentAttributes($type)
    {
        try {
            $collection = $this->getTableColumnList('sales_flat_order_payment');
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Could not load Magento payment schema...");
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
        }
        if ($collection) {
            $this->_cache[$type]['payment'] = array(
                'label' => 'Payment',
                'value' => array()
            );
            foreach ($collection as $_attribute) {
                $key = $_attribute['Field'];
                $this->_cache[$type]['payment']['value'][] = array(
                    'value' => 'Payment : ' . $key,
                    'label' => 'Payment : ' . ucwords(str_replace("_", " ", $key)),
                );
            }
        }
    }

    protected function _populateCustomAttributes($type)
    {
        $this->_cache[$type]['custom'] = array(
            'label' => 'Custom',
            'value' => array()
        );
        $this->_cache[$type]['custom']['value'] = array(
            array(
                'value' => 'Custom : current_url',
                'label' => 'Custom : Current URL'
            ),
            array(
                'value' => 'Custom : todays_date',
                'label' => 'Custom : Todays Date'
            ),
            array(
                'value' => 'Custom : todays_timestamp',
                'label' => 'Custom : Todays Date + Time'
            ),
            array(
                'value' => 'Custom : end_of_month',
                'label' => 'Custom : End of Month Date'
            ),
            array(
                'value' => 'Custom : store_view_name',
                'label' => 'Custom : Store View Name'
            ),
            array(
                'value' => 'Custom : store_group_name',
                'label' => 'Custom : Store Group Name'
            ),
            array(
                'value' => 'Custom : website_name',
                'label' => 'Custom : Website Name'
            ),
            array(
                'value' => 'Custom : field',
                'label' => 'Custom Field'
            )
        );
    }

    /**
     * @param $type
     */
    protected function _populateCustomerAttributes($type)
    {
        $collection = Mage::getResourceModel('customer/attribute_collection');
        if (Mage::helper('tnw_salesforce')->getMagentoVersion() < 1500) {
            $collection->addVisibleFilter();
        } else {
            $collection->addSystemHiddenFilter();
        }
        $this->_cache[$type]['customer'] = array(
            'label' => 'Customer',
            'value' => array(),
        );
        foreach ($collection as $_attribute) {
            if ($_attribute->frontend_label != "") {
                $this->_cache[$type]['customer']['value'][] = array(
                    'value' => 'Customer : ' . $_attribute->attribute_code,
                    'label' => 'Customer : ' . $_attribute->frontend_label,
                );
            }
        }
        unset($collection, $_attribute);
    }

    /**
     * @param $type
     */
    protected function _populateCustomerGroupAttributes($type)
    {
        $collection = Mage::getResourceModel('customer/group_collection');
        $this->_cache[$type]['customer_group'] = array(
            'label' => 'Customer Group',
            'value' => array(),
        );
        foreach ($collection as $_attribute) {
            foreach ($_attribute->getData() as $key => $_attribute) {
                // skip Customer Group : customer_group_id
                if ($key == 'customer_group_id') {
                    continue;
                }

                $this->_cache[$type]['customer_group']['value'][] = array(
                    'value' => 'Customer Group : ' . $key,
                    'label' => 'Customer Group : ' . ucwords(str_replace("_", " ", $key)),
                );
            }
            break;
        }
    }

    /**
     * @param string $addressType
     * @param $type
     */
    protected function _populateAddressAttributes($addressType = 'custom', $type)
    {
        $collection = Mage::getResourceModel('customer/address_attribute_collection');

        $this->_cache[$type][$addressType] = array(
            'label' => ucwords($addressType) . " Address",
            'value' => array()
        );
        foreach ($collection as $_attribute) {
            if ($_attribute->is_visible && $_attribute->frontend_label != "" && $_attribute->frontend_input != "hidden") {
                $this->_cache[$type][$addressType]['value'][] = array(
                    'value' => ucwords($addressType) . ' : ' . $_attribute->attribute_code,
                    'label' => ucwords($addressType) . ' : ' . $_attribute->frontend_label,
                );
            }
        }
        unset($collection, $_attribute);
    }

    protected function _populateAitocAttributes($type)
    {
        $fieldsType = 'aitoc_checkout';
        $oResource = Mage::getResourceModel('eav/entity_attribute');
        $collection = Mage::getResourceModel('eav/entity_attribute_collection')
            ->setEntityTypeFilter(Mage::getModel('eav/entity')->setType($fieldsType)->getTypeId());
        $collection->getSelect()->join(
            array('additional_table' => $oResource->getTable('catalog/eav_attribute')),
            'additional_table.attribute_id=main_table.attribute_id'
        );
        $this->_cache[$type][$fieldsType] = array(
            'label' => 'Aitoc',
            'value' => array(),
        );

        $_count = 0;
        foreach ($collection as $_attribute) {
            if (
                $type != TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT
                && $type != TNW_Salesforce_Model_Config_Objects::ORDER_OBJECT
                && $_attribute->getAitRegistrationPage() == "0"
            ) {
                continue; //Skip mapping of order fields if those are not avaialble during registration
            }
            if ($_attribute->is_visible) {
                $this->_cache[$type][$fieldsType]['value'][] = array(
                    'value' => 'Aitoc : ' . $_attribute->attribute_code,
                    'label' => 'Aitoc : ' . $_attribute->frontend_label,
                );
                $_count++;
            }
        }
        if ($_count < 1) {
            unset($this->_cache[$type][$fieldsType]);
        }
        unset($collection, $_attribute);
    }

    public function _populateCartAttributes($type)
    {
        try {
            $collection = $this->getTableColumnList('sales_flat_order_item');
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Could not load Magento cart items schema...");
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
        }
        if ($collection) {
            $this->_cache[$type]['shopping'] = array(
                'label' => 'Cart Attributes',
                'value' => array()
            );
            foreach ($collection as $_attribute) {
                $key = $_attribute['Field'];
                if (
                    $key == 'item_id'
                    || $key == 'order_id'
                    || $key == 'parent_item_id'
                    || $key == 'quote_item_id'
                ) {
                    continue;
                }

                $this->_cache[$type]['shopping']['value'][] = array(
                    'value' => 'Cart : ' . $key,
                    'label' => 'Cart : ' . ucwords(str_replace("_", " ", $key)),
                );
            }
        }
    }

    public function _populateAbandonedItemAttributes($type)
    {
        try {
            $collection = $this->getTableColumnList('sales_flat_quote_item');
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Could not load Magento quote items schema...");
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
        }
        if ($collection) {
            $this->_cache[$type]['shopping'] = array(
                'label' => 'Abandoned Cart items attributes',
                'value' => array()
            );
            foreach ($collection as $_attribute) {
                $key = $_attribute['Field'];
                if (
                    $key == 'item_id'
                    || $key == 'quote_id'
                    || $key == 'parent_item_id'
                    || $key == 'quote_item_id'
                ) {
                    continue;
                }

                $this->_cache[$type]['abandoned_items']['value'][] = array(
                    'value' => 'Item : ' . $key,
                    'label' => 'Item : ' . ucwords(str_replace("_", " ", $key)),
                );
            }
        }
    }

    public function _populateInvoiceItemAttributes($type)
    {
        try {
            $collection = $this->getTableColumnList('sales_flat_invoice_item');
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Could not load Magento quote items schema...");
            Mage::helper('tnw_salesforce')->log("ERROR: " . $e->getMessage());
        }
        if ($collection) {
            $this->_cache[$type]['invoice_items'] = array(
                'label' => 'Invoice item attributes',
                'value' => array()
            );
            foreach ($collection as $_attribute) {
                $key = $_attribute['Field'];
                if (
                    $key == 'entity_id'
                    || $key == 'parent_id'
                    || $key == 'product_id'
                    || $key == 'order_item_id'
                    || $key == 'salesforce_id'
                ) {
                    continue;
                }

                $this->_cache[$type]['invoice_items']['value'][] = array(
                    'value' => 'Billing Item : ' . $key,
                    'label' => 'Billing Item : ' . ucwords(str_replace("_", " ", $key)),
                );
            }
        }
    }

    /**
     * @param $type
     * @return bool
     */
    public function _populateProductAttributes($type)
    {
        $collection = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addVisibleFilter();

        $this->_cache[$type]['product'] = array(
            'label' => 'Product',
            'value' => array(),
        );
        foreach ($collection as $_attribute) {
            if ($_attribute->frontend_label != "" && $_attribute->is_visible) {
                $this->_cache[$type]['product']['value'][] = array(
                    'value' => 'Product : ' . $_attribute->attribute_code,
                    'label' => 'Product : ' . $_attribute->frontend_label,
                );
            }
        }

        $this->_cache[$type]['product']['value'][] = array(
            'value' => 'Product : website_ids',
            'label' => 'Product : Website Ids',
        );

        // add product type
        $this->_cache[$type]['product']['value'][] = array(
            'value' => 'Product : type_id', // do not use type_Id cause error happens
            'label' => 'Product : Product Type',
        );

        // add product type
        $this->_cache[$type]['product']['value'][] = array(
            'value' => 'Product : attribute_set_id', // do not use type_Id cause error happens
            'label' => 'Product : Attribute Set',
        );

        // add inventory field list
        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $this->_cache[$type]['inventory'] = array(
                'label' => 'Product Inventory',
                'value' => array(),
            );

            foreach ($this->getTableColumnList() as $one) {
                $this->_cache[$type]['inventory']['value'][] = array(
                    'value' => "Product Inventory : {$one['Field']}",
                    'label' => 'Product Inventory : '.$this->toName($one['Field']),
                );
            }
        }

        unset($collection, $_attribute);
    }

    /**
     * @param string $tableName
     * @return mixed
     */
    protected function getTableColumnList($tableName = 'cataloginventory_stock_item')
    {
        $sql = "DESCRIBE " . Mage::helper('tnw_salesforce')->getTable($tableName) . ";";
        $res = $this->getDbConnection('read')->query($sql)->fetchAll();

        return !empty($res) ? $res : array();
    }

    /**
     * convert underscored to words
     *
     * @param $value
     * @return mixed
     */
    public function toName($value)
    {
        return ucwords(preg_replace('/_/', ' ', $value));
    }
}