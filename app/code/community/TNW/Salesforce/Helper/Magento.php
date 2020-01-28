<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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
            TNW_Salesforce_Model_Config_Objects::INVOICE_OBJECT,
            TNW_Salesforce_Model_Config_Objects::SHIPMENT_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_CREDIT_MEMO_OBJECT,
        );
        $this->_acl['orderItem'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_ITEM_OBJECT,
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
            TNW_Salesforce_Model_Config_Objects::ACCOUNT_OBJECT,
            TNW_Salesforce_Model_Config_Objects::SHIPMENT_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_CREDIT_MEMO_OBJECT,
            'Wishlist',
        );

        $this->_acl['product'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ABANDONED_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::PRODUCT_OBJECT,
            'Product2',
            TNW_Salesforce_Model_Config_Objects::INVOICE_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_CREDIT_MEMO_ITEM_OBJECT,
            'WishlistItem',
        );

        $this->_acl['invoice'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::INVOICE_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT,
        );
        $this->_acl['invoiceItem'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::INVOICE_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_ITEM_OBJECT
        );

        $this->_acl['shipment'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::SHIPMENT_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_OBJECT,
        );
        $this->_acl['shipmentItem'] = array(
            0 => TNW_Salesforce_Model_Config_Objects::SHIPMENT_ITEM_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_ITEM_OBJECT,
        );

        $this->_acl['creditMemo'] = array(
            TNW_Salesforce_Model_Config_Objects::ORDER_CREDIT_MEMO_OBJECT,
        );

        $this->_acl['creditMemoItem'] = array(
            TNW_Salesforce_Model_Config_Objects::ORDER_CREDIT_MEMO_ITEM_OBJECT,
        );

        $this->_acl['catalogrule'] = array(
            'Catalogrule'
        );

        $this->_acl['salesrule'] = array(
            'Salesrule'
        );

        $this->_acl['wishlist'] = array(
            'Wishlist',
            'WishlistItem'
        );

        $this->_acl['wishlistItem'] = array(
            'WishlistItem'
        );
    }
    /**
     * @param null $type
     * @return array
     */
    public function getMagentoAttributes($type = NULL)
    {
        if (!$type) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Magento fields drop down failed to create, missing type");
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

        if (in_array($type, $this->_acl['invoiceItem'])) {
            $this->_populateInvoiceItemAttributes($type);
        }

        if (in_array($type, $this->_acl['shipment'])) {
            $this->_populateShipmentAttributes($type);
        }

        if (in_array($type, $this->_acl['shipmentItem'])) {
            $this->_populateShipmentItemAttributes($type);
        }

        if (in_array($type, $this->_acl['creditMemo'])) {
            $this->_populateCreditMemoAttributes($type);
        }

        if (in_array($type, $this->_acl['creditMemoItem'])) {
            $this->_populateCreditMemoItemAttributes($type);
        }

        if (in_array($type, $this->_acl['order'])) {
            $this->_populateOrderAttributes($type);
            $this->_populatePaymentAttributes($type);
        }

        if (in_array($type, $this->_acl['orderItem'])) {
            /* Order Item */
            $this->_populateOrderItemAttributes($type);
        }

        if (in_array($type, $this->_acl['abandoned'])) {
            $this->_populateAbandonedAttributes($type);
        }

        if (in_array($type, $this->_acl['abandonedItem'])) {
            $this->_populateAbandonedItemAttributes($type);
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

        if (in_array($type, $this->_acl['product'])) {
            /* Product Attributes */
            $this->_populateProductAttributes($type);
        }

        if (in_array($type, $this->_acl['catalogrule'])) {
            $this->_populateCatalogruleAttributes($type);
        }

        if (in_array($type, $this->_acl['salesrule'])) {
            $this->_populateSalesruleAttributes($type);
        }

        if (in_array($type, $this->_acl['wishlist'])) {
            $this->_populateWishlistAttributes($type);
        }

        if (in_array($type, $this->_acl['wishlistItem'])) {
            $this->_populateWishlistItemAttributes($type);
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
            $collection = $this->getDbConnection('read')
                ->describeTable(Mage::helper('tnw_salesforce')->getTable('sales_flat_order'));
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not load Magento order schema...");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            return;
        }

        if ($collection) {
            $this->_cache[$type]['order'] = array(
                'label' => 'Order',
                'value' => array()
            );

            $_additionalAttributes = array(
                'number'            => 'Number',
                'cart_all'          => 'All Cart Items (Text)',
                'payment_method'    => 'Payment Method Label',
                'notes'             => 'Status Notes (Admin)',
                'website'           => 'Associate to Website',
                'sf_status'         => 'Salesforce Status',
                'sf_name'           => 'Salesforce Name',
                'price_book'        => 'Price Book'
            );

            foreach ($_additionalAttributes as $value => $label) {
                $this->_cache[$type]['order']['value'][] = array(
                    'value' => 'Order : '.$value,
                    'label' => 'Order : '.$label,
                );
            }

            foreach ($collection as $_attribute) {
                $key = $_attribute['COLUMN_NAME'];
                if (in_array($key, array('increment_id', 'sf_insync', 'salesforce_id'))) {
                    continue;
                }

                $this->_cache[$type]['order']['value'][] = array(
                    'value' => 'Order : ' . $key,
                    'label' => 'Order : ' . $this->toName($key),
                );
            }
        }
    }

    protected function _populateInvoiceAttributes($type)
    {
        try {
            $collection = $this->getDbConnection('read')
                ->describeTable(Mage::helper('tnw_salesforce')->getTable('sales_flat_invoice'));
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not load Magento order schema...");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            return;
        }

        if ($collection) {
            $this->_cache[$type]['invoice'] = array(
                'label' => 'Invoice',
                'value' => array()
            );

            $_additionalAttributes = array(
                'number'            => 'Number',
                'cart_all'          => 'All Cart Items (Text)',
                'website'           => 'Associate to Website',
                'sf_status'         => 'Salesforce Status',
                'sf_name'           => 'Salesforce Name',
            );

            foreach ($_additionalAttributes as $value => $label) {
                $this->_cache[$type]['invoice']['value'][] = array(
                    'value' => 'Invoice : '.$value,
                    'label' => 'Invoice : '.$label,
                );
            }

            foreach ($collection as $_attribute) {
                $key = $_attribute['COLUMN_NAME'];
                if (in_array($key, array(
                    'entity_id', 'sf_insync', 'salesforce_id',
                    'can_void_flag', 'shipping_address_id',
                    'billing_address_id', 'increment_id', 'transaction_id'))
                ) {
                    continue;
                }

                $this->_cache[$type]['invoice']['value'][] = array(
                    'value' => 'Invoice : ' . $key,
                    'label' => 'Invoice : ' . $this->toName($key),
                );
            }
        }
    }

    protected function _populateShipmentAttributes($type)
    {
        try {
            $collection = $this->getDbConnection('read')
                ->describeTable(Mage::getModel('sales/order_shipment')->getResource()->getMainTable());
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not load Magento shipment schema...");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            return;
        }

        if ($collection) {
            $this->_cache[$type]['shipment'] = array(
                'label' => 'Shipment',
                'value' => array()
            );

            $_additionalAttributes = array(
                'number'            => 'Number',
                'cart_all'          => 'All Cart Items (Text)',
                'website'           => 'Associate to Website',
                'sf_status'         => 'Salesforce Status',
                'sf_name'           => 'Salesforce Name',
                'track_number'      => 'Track Number',
            );

            foreach ($_additionalAttributes as $value => $label) {
                $this->_cache[$type]['shipment']['value'][] = array(
                    'value' => 'Shipment : '.$value,
                    'label' => 'Shipment : '.$label,
                );
            }

            foreach ($collection as $_attribute) {
                $key = $_attribute['COLUMN_NAME'];
                if (in_array($key, array(
                    'entity_id', 'store_id',
                    'order_id', 'customer_id',
                    'shipping_address_id', 'billing_address_id',
                    'sf_insync', 'salesforce_id',
                ))) {
                    continue;
                }

                $this->_cache[$type]['shipment']['value'][] = array(
                    'value' => 'Shipment : ' . $key,
                    'label' => 'Shipment : ' . $this->toName($key),
                );
            }
        }
    }

    protected function _populateCreditMemoAttributes($type)
    {
        try {
            $collection = $this->getDbConnection('read')
                ->describeTable(Mage::getModel('sales/order_creditmemo')->getResource()->getMainTable());
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not load Magento shipment schema...");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            return;
        }

        if ($collection) {
            $this->_cache[$type]['creditmemo'] = array(
                'label' => 'Credit Memo',
                'value' => array()
            );

            $_additionalAttributes = array(
                'number'            => 'Number',
                'cart_all'          => 'All Cart Items (Text)',
                'website'           => 'Associate to Website',
                'sf_status'         => 'Salesforce Status',
                'sf_name'           => 'Salesforce Name',
                'track_number'      => 'Track Number',
            );

            foreach ($_additionalAttributes as $value => $label) {
                $this->_cache[$type]['creditmemo']['value'][] = array(
                    'value' => 'Credit Memo : '.$value,
                    'label' => 'Credit Memo : '.$label,
                );
            }

            foreach ($collection as $_attribute) {
                $key = $_attribute['COLUMN_NAME'];
                if (in_array($key, array(
                    'entity_id', 'store_id',
                    'order_id', 'customer_id',
                    'shipping_address_id', 'billing_address_id',
                    'sf_insync', 'salesforce_id',
                ))) {
                    continue;
                }

                $this->_cache[$type]['creditmemo']['value'][] = array(
                    'value' => 'Credit Memo : ' . $key,
                    'label' => 'Credit Memo : ' . $this->toName($key),
                );
            }
        }
    }

    protected function _populateAbandonedAttributes($type)
    {
        try {
            $collection = $this->getDbConnection('read')
                ->describeTable(Mage::helper('tnw_salesforce')->getTable('sales_flat_quote'));
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not load Magento order schema...");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            return;
        }

        if ($collection) {
            $this->_cache[$type]['abandoned'] = array(
                'label' => 'Cart Attribute',
                'value' => array()
            );

            $_additionalAttributes = array(
                'number'            => 'Number',
                'cart_all'          => 'All Cart Items (Text)',
                'website'           => 'Associate to Website',
                'sf_stage'          => 'Salesforce Stage',
                'sf_name'           => 'Salesforce Name',
                'price_book'        => 'Price Book',
                'sf_close_date'        => 'Close Date',
                'owner_salesforce_id'=> 'owner_salesforce_id'
            );

            foreach ($_additionalAttributes as $value => $label) {
                $this->_cache[$type]['abandoned']['value'][] = array(
                    'value' => 'Cart : '.$value,
                    'label' => 'Cart : '.$label,
                );
            }

            foreach ($collection as $_attribute) {
                $key = $_attribute['COLUMN_NAME'];
                if (in_array($key, array('increment_id', 'sf_insync', 'salesforce_id', 'status'))) {
                    continue;
                }

                $this->_cache[$type]['abandoned']['value'][] = array(
                    'value' => 'Cart : ' . $key,
                    'label' => 'Cart : ' . $this->toName($key),
                );
            }
        }
    }

    protected function _populatePaymentAttributes($type)
    {
        try {
            $collection = $this->getDbConnection('read')
                ->describeTable(Mage::helper('tnw_salesforce')->getTable('sales_flat_order_payment'));
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not load Magento payment schema...");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            return;
        }

        if ($collection) {
            $this->_cache[$type]['payment'] = array(
                'label' => 'Payment',
                'value' => array()
            );

            foreach ($collection as $_attribute) {
                $key = $_attribute['COLUMN_NAME'];
                $this->_cache[$type]['payment']['value'][] = array(
                    'value' => 'Payment : ' . $key,
                    'label' => 'Payment : ' . $this->toName($key),
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
                'value' => 'Custom : todays_plus_day',
                'label' => 'Custom : Today + # of Days'
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

        $_additionalAttributes = array(
            'id'               => 'Id',
            'sf_record_type'   => 'Record Type',
            'sf_email_opt_out' => 'Email Opt Out',
            'sf_company'       => 'Company'
        );

        foreach ($_additionalAttributes as $value => $label) {
            $this->_cache[$type]['customer']['value'][] = array(
                'value' => 'Customer : '.$value,
                'label' => 'Customer : '.$label,
            );
        }

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
                    'label' => 'Customer Group : ' . $this->toName($key),
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
            if ($_attribute->is_visible && $_attribute->frontend_label != "") {

                $label = $_attribute->frontend_label;
                if ($_attribute->frontend_input == "hidden") {
                    $label = $label . ' (Id/Code)';
                }
                $this->_cache[$type][$addressType]['value'][] = array(
                    'value' => ucwords($addressType) . ' : ' . $_attribute->attribute_code,
                    'label' => ucwords($addressType) . ' : ' . $label,
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

    public function _populateOrderItemAttributes($type)
    {
        try {
            $collection = $this->getDbConnection('read')
                ->describeTable(Mage::helper('tnw_salesforce')->getTable('sales_flat_order_item'));
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not load Magento cart items schema...");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            return;
        }

        if ($collection) {
            $this->_cache[$type]['order_items'] = array(
                'label' => 'Order Item Attributes',
                'value' => array()
            );

            $_additionalAttributes = array(
                'unit_price_excluding_tax_and_discounts'        => 'Unit Price (excluding Tax and Discounts)',
                'unit_price_including_tax_excluding_discounts'  => 'Unit Price including Tax (excluding Discounts)',
                'unit_price_including_discounts_excluding_tax'  => 'Unit Price including Discounts (excluding Tax)',
                'unit_price_including_tax_and_discounts'        => 'Unit Price including Tax and Discounts',
                'sf_product_options_html'                       => 'Product Options (HTML)',
                'sf_product_options_text'                       => 'Product Options (Text)',
            );

            foreach ($_additionalAttributes as $value => $label) {
                $this->_cache[$type]['order_items']['value'][] = array(
                    'value' => 'Order Item : '.$value,
                    'label' => 'Order Item : '.$label,
                );
            }

            foreach ($collection as $_attribute) {
                $key = $_attribute['COLUMN_NAME'];
                if (in_array($key, array('order_id', 'parent_item_id', 'quote_item_id', 'product_options'))) {
                    continue;
                }

                $this->_cache[$type]['order_items']['value'][] = array(
                    'value' => 'Order Item : ' . $key,
                    'label' => 'Order Item : ' . $this->toName($key),
                );
            }
        }
    }

    public function _populateAbandonedItemAttributes($type)
    {
        try {
            $collection = $this->getDbConnection('read')
                ->describeTable(Mage::helper('tnw_salesforce')->getTable('sales_flat_quote_item'));
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not load Magento quote items schema...");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            return;
        }

        if ($collection) {
            $this->_cache[$type]['abandoned_items'] = array(
                'label' => 'Abandoned Cart items',
                'value' => array()
            );

            $_additionalAttributes = array(
                'unit_price_excluding_tax_and_discounts'        => 'Unit Price (excluding Tax and Discounts)',
                'unit_price_including_tax_excluding_discounts'  => 'Unit Price including Tax (excluding Discounts)',
                'unit_price_including_discounts_excluding_tax'  => 'Unit Price including Discounts (excluding Tax)',
                'unit_price_including_tax_and_discounts'        => 'Unit Price including Tax and Discounts',
                'sf_product_options_html'                       => 'Product Options (HTML)',
                'sf_product_options_text'                       => 'Product Options (Text)',
            );

            foreach ($_additionalAttributes as $value => $label) {
                $this->_cache[$type]['abandoned_items']['value'][] = array(
                    'value' => 'Cart Item : '.$value,
                    'label' => 'Cart Item : '.$label,
                );
            }

            foreach ($collection as $_attribute) {
                $key = $_attribute['COLUMN_NAME'];
                if (in_array($key, array('item_id', 'quote_id', 'parent_item_id', 'quote_item_id'))) {
                    continue;
                }

                $this->_cache[$type]['abandoned_items']['value'][] = array(
                    'value' => 'Cart Item : ' . $key,
                    'label' => 'Cart Item : ' . $this->toName($key),
                );
            }
        }
    }

    public function _populateInvoiceItemAttributes($type)
    {
        try {
            $collection = $this->getDbConnection('read')
                ->describeTable(Mage::helper('tnw_salesforce')->getTable('sales_flat_invoice_item'));
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not load Magento quote items schema...");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            return;
        }

        if ($collection) {
            $this->_cache[$type]['invoice_items'] = array(
                'label' => 'Invoice item attributes',
                'value' => array()
            );

            $_additionalAttributes = array(
                'number'                                        => 'Number',
                'unit_price_excluding_tax_and_discounts'        => 'Unit Price (excluding Tax and Discounts)',
                'unit_price_including_tax_excluding_discounts'  => 'Unit Price including Tax (excluding Discounts)',
                'unit_price_including_discounts_excluding_tax'  => 'Unit Price including Discounts (excluding Tax)',
                'unit_price_including_tax_and_discounts'        => 'Unit Price including Tax and Discounts',
                'sf_product_options_html'                       => 'Product Options (HTML)',
                'sf_product_options_text'                       => 'Product Options (Text)',
            );

            foreach ($_additionalAttributes as $value => $label) {
                $this->_cache[$type]['invoice_items']['value'][] = array(
                    'value' => 'Billing Item : '.$value,
                    'label' => 'Billing Item : '.$label,
                );
            }

            foreach ($collection as $_attribute) {
                $key = $_attribute['COLUMN_NAME'];
                if (in_array($key, array('entity_id', 'parent_id', 'product_id', 'order_item_id', 'salesforce_id'))) {
                    continue;
                }

                $this->_cache[$type]['invoice_items']['value'][] = array(
                    'value' => 'Billing Item : ' . $key,
                    'label' => 'Billing Item : ' . $this->toName($key),
                );
            }
        }
    }

    public function _populateShipmentItemAttributes($type)
    {
        try {
            $collection = $this->getDbConnection('read')
                ->describeTable(Mage::helper('tnw_salesforce')->getTable('sales_flat_shipment_item'));
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not load Magento quote items schema...");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            return;
        }

        if ($collection) {
            $this->_cache[$type]['shipment_items'] = array(
                'label' => 'Shipment item attributes',
                'value' => array()
            );

            $_additionalAttributes = array(
                'number'                  => 'Number',
                'sf_product_options_html' => 'Product Options (HTML)',
                'sf_product_options_text' => 'Product Options (Text)',
            );

            foreach ($_additionalAttributes as $value => $label) {
                $this->_cache[$type]['shipment_items']['value'][] = array(
                    'value' => 'Shipment Item : '.$value,
                    'label' => 'Shipment Item : '.$label,
                );
            }

            foreach ($collection as $_attribute) {
                $key = $_attribute['COLUMN_NAME'];
                if (in_array($key, array('entity_id', 'parent_id', 'product_id', 'order_item_id', 'salesforce_id'))) {
                    continue;
                }

                $this->_cache[$type]['shipment_items']['value'][] = array(
                    'value' => 'Shipment Item : ' . $key,
                    'label' => 'Shipment Item : ' . $this->toName($key),
                );
            }
        }
    }

    public function _populateCreditMemoItemAttributes($type)
    {
        try {
            $collection = $this->getDbConnection('read')
                ->describeTable(Mage::helper('tnw_salesforce')->getTable('sales_flat_creditmemo_item'));
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Could not load Magento quote items schema...");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());

            return;
        }

        if ($collection) {
            $this->_cache[$type]['creditmemo_items'] = array(
                'label' => 'Credit Memo item attributes',
                'value' => array()
            );

            $_additionalAttributes = array(
                'number'                                        => 'Number',
                'unit_price_excluding_tax_and_discounts'        => 'Unit Price (excluding Tax and Discounts)',
                'unit_price_including_tax_excluding_discounts'  => 'Unit Price including Tax (excluding Discounts)',
                'unit_price_including_discounts_excluding_tax'  => 'Unit Price including Discounts (excluding Tax)',
                'unit_price_including_tax_and_discounts'        => 'Unit Price including Tax and Discounts',
                'sf_product_options_html'                       => 'Product Options (HTML)',
                'sf_product_options_text'                       => 'Product Options (Text)',
            );

            foreach ($_additionalAttributes as $value => $label) {
                $this->_cache[$type]['creditmemo_items']['value'][] = array(
                    'value' => 'Credit Memo Item : '.$value,
                    'label' => 'Credit Memo Item : '.$label,
                );
            }

            foreach ($collection as $_attribute) {
                $key = $_attribute['COLUMN_NAME'];
                if (in_array($key, array('entity_id', 'parent_id', 'product_id', 'order_item_id', 'salesforce_id'))) {
                    continue;
                }

                $this->_cache[$type]['creditmemo_items']['value'][] = array(
                    'value' => 'Credit Memo Item : ' . $key,
                    'label' => 'Credit Memo Item : ' . $this->toName($key),
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

        // add product type
        $this->_cache[$type]['product']['value'][] = array(
            'value' => 'Product : product_url', // do not use type_Id cause error happens
            'label' => 'Product : Url of the product page',
        );

        // add product number
        $this->_cache[$type]['product']['value'][] = array(
            'value' => 'Product : id', // do not use type_Id cause error happens
            'label' => 'Product : Id',
        );

        // add inventory field list
        $this->_cache[$type]['inventory'] = array(
            'label' => 'Product Inventory',
            'value' => array(),
        );

        $collection = $this->getDbConnection('read')
            ->describeTable(Mage::helper('tnw_salesforce')->getTable('cataloginventory_stock_item'));

        foreach ($collection as $one) {
            $this->_cache[$type]['inventory']['value'][] = array(
                'value' => "Product Inventory : {$one['COLUMN_NAME']}",
                'label' => 'Product Inventory : '.$this->toName($one['COLUMN_NAME']),
            );
        }

        unset($collection, $_attribute);
    }

    public function _populateCatalogruleAttributes($type)
    {
        try {
            /** @var Mage_CatalogRule_Model_Resource_Rule $resource */
            $resource = Mage::getResourceModel('catalogrule/rule');
            $describe = $resource->getReadConnection()
                ->describeTable($resource->getMainTable());

            $this->_cache[$type]['catalog_rule'] = array(
                'label' => 'Catalog rule attributes',
                'value' => $this->prepareAttributes($describe, 'Catalog Rule', array('number' => 'Number'), array('salesforce_id'))
            );
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Could not load Magento quote items schema...');
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: {$e->getMessage()}");
        }
    }

    public function _populateSalesruleAttributes($type)
    {
        try {
            /** @var Mage_SalesRule_Model_Resource_Rule $resource */
            $resource = Mage::getResourceModel('salesrule/rule');
            $describe = $resource->getReadConnection()
                ->describeTable($resource->getMainTable());

            $this->_cache[$type]['sales_rule'] = array(
                'label' => 'Shopping Cart Rule',
                'value' => $this->prepareAttributes($describe, 'Shopping Cart Rule', array('number' => 'Number'), array('salesforce_id'))
            );
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Could not load Magento quote items schema...');
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: {$e->getMessage()}");
        }
    }

    public function _populateWishlistAttributes($type)
    {
        try {
            $resource = Mage::getResourceModel('wishlist/wishlist');
            $describe = $resource->getReadConnection()
                ->describeTable($resource->getMainTable());

            $_additionalAttributes = array(
                'number' => 'Number',
                'cart_all' => 'All Cart Items (Text)',
                'website' => 'Associate to Website',
                'sf_stage' => 'Salesforce Stage',
                'sf_name' => 'Salesforce Name',
                'sf_close_date' => 'Close Date',
                'owner_salesforce_id' => 'Salesforce Owner Id'
            );

            $this->_cache[$type]['wishlist'] = array(
                'label' => 'Wishlist',
                'value' => $this->prepareAttributes($describe, 'Wishlist', $_additionalAttributes, array('salesforce_id'))
            );
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Could not load Magento quote items schema...');
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: {$e->getMessage()}");
        }
    }

    public function _populateWishlistItemAttributes($type)
    {
        try {
            /** @var Mage_Wishlist_Model_Resource_Item $resource */
            $resource = Mage::getResourceModel('wishlist/item');
            $describe = $resource->getReadConnection()
                ->describeTable($resource->getMainTable());

            $_additionalAttributes = array(
                'sf_product_options_html'                       => 'Product Options (HTML)',
                'sf_product_options_text'                       => 'Product Options (Text)',
            );

            $this->_cache[$type]['wishlistItem'] = array(
                'label' => 'Wishlist Item',
                'value' => $this->prepareAttributes($describe, 'Wishlist Item', $_additionalAttributes)
            );
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('Could not load Magento quote items schema...');
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: {$e->getMessage()}");
        }
    }

    /**
     * @param array $describe
     * @param $fieldLabel
     * @param array $additionalAttributes
     * @param array $excludeAttributes
     * @return array
     */
    protected function prepareAttributes(array $describe, $fieldLabel, array $additionalAttributes = array(), array $excludeAttributes = array())
    {
        $fields = array();
        foreach ($additionalAttributes as $value => $label) {
            $fields[] = array(
                'value' => "$fieldLabel: $value",
                'label' => "$fieldLabel: $label",
            );
        }

        foreach ($describe as $_attribute) {
            $key = $_attribute['COLUMN_NAME'];
            if (in_array($key, $excludeAttributes)) {
                continue;
            }

            $fields[] = array(
                'value' => "$fieldLabel: $key",
                'label' => "$fieldLabel: {$this->toName($key)}",
            );
        }

        return $fields;
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