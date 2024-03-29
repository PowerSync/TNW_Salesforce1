<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Data extends TNW_Salesforce_Helper_Abstract
{
    /* API Config */
    const API_ENABLED = 'salesforce/api_config/api_enable';
    const API_USERNAME = 'salesforce/api_config/api_username';
    const API_PASSWORD = 'salesforce/api_config/api_password';
    const API_TOKEN = 'salesforce/api_config/api_token';
    const API_WSDL = 'salesforce/api_config/api_wsdl';

    /**
     * @comment Base batch limit for simple sync
     */
    const BASE_UPDATE_LIMIT = 200;
    /**
     * @comment Base batch Lead conversion limit for simple sync
     */
    const BASE_CONVERT_LIMIT = 100;

    /* License Configuration */
    const API_LICENSE_EMAIL = 'salesforce/api_license/api_email';
    const API_LICENSE_INVOICE = 'salesforce/api_license/api_invoice';
    const API_LICENSE_TRANSACTION = 'salesforce/api_license/api_transaction';

    /* API Developer */
    const API_LOG = 'salesforce/development_and_debugging/log_enable';
    const FAIL_EMAIL = 'salesforce/developer/fail_order';
    const FAIL_EMAIL_SUBJECT = 'salesforce/developer/email_prefix';
    const REAL_TIME_SYNC_MAX_COUNT = 'salesforce/development_and_debugging/real_time_sync_max_count';
    const BULK_RESULT_MAX_ATTENTIONS = 'salesforce/development_and_debugging/bulk_result_max_attentions';

    /* Product */
    const PRODUCT_SYNC = 'salesforce_product/general/product_enable';
    const PRODUCT_PRICEBOOK = 'salesforce_product/general/default_pricebook';

    // newsletter related
    const CUSTOMER_NEWSLETTER_SYNC = 'salesforce_customer/newsletter_config/customer_newsletter_enable_sync';
    const CUSTOMER_CAMPAIGN_ID = 'salesforce_customer/newsletter_config/customer_newletter_campaign_id';

    /* Order Config */
    const ORDER_INTEGRATION_TYPE = 'salesforce_order/customer_opportunity/integration_type';
    const ORDER_INTEGRATION_OPTION = 'salesforce_order/customer_opportunity/integration_option';
    const ORDER_SYNC = 'salesforce_order/general/order_sync_enable';
    const ORDER_PRODUCT_SYNC = 'salesforce_order/general/order_product_enable';
    const ORDER_MULTI_CURRENCY = 'salesforce_order/currency/multi_currency';
    const ORDER_STATUS_ALL = 'salesforce_order/general/order_status_all';
    const ORDER_STATUS_ALLOW = 'salesforce_order/general/order_status_allow';
    const ORDER_CUSTOMER_ADDRESS = 'salesforce_order/general/customer_address';

    // Notes Config
    const NONES_SYNC = 'salesforce_order/general/notes_synchronize';

    // Campaigns Config
    const CAMPAIGNS_SYNC = 'salesforce_promotion/salesforce_campaigns/sync_enabled';
    const CAMPAIGNS_CREATE_AUTOMATE = 'salesforce_promotion/salesforce_campaigns/create_campaign_automatic';

    /* Order Customer Role */
    const ORDER_CREATE_REVERSE_SYNC = 'salesforce_order/customer_opportunity/order_create_reverse_sync';
    const ORDER_CREATE_REVERSE_SYNC_PAYMENT = 'salesforce_order/customer_opportunity/order_create_reverse_sync_payment';
    const ORDER_CREATE_REVERSE_SYNC_SHIPPING = 'salesforce_order/customer_opportunity/order_create_reverse_sync_shipping';
    const CUSTOMER_ROLE_ENABLED = 'salesforce_order/customer_opportunity/customer_opportunity_role_enable';
    const CUSTOMER_ROLE = 'salesforce_order/customer_opportunity/customer_integration_opp';

    /* queue object sync settings */
    const OBJECT_SYNC_TYPE = 'salesforce/syncronization/sync_type_realtime';

    // last cron run time
    const CRON_LAST_RUN_TIMESTAMP = 'salesforce/syncronization/cron_last_run_timestamp';

    /* Order Use Opportunity Products */
    //const ORDER_USE_PRODUCTS = 'salesforce_order/opportunity_cart/use_products';
    const ORDER_USE_TAX_PRODUCT = 'salesforce_order/opportunity_cart/use_tax_product';
    const ORDER_TAX_PRODUCT = 'salesforce_order/opportunity_cart/tax_product_pricebook';
    const ORDER_UPDATE_TAX_TOTAL = 'salesforce_order/opportunity_cart/update_tax_total';

    const ORDER_USE_SHIPPING_PRODUCT = 'salesforce_order/opportunity_cart/use_shipping_product';
    const ORDER_SHIPPING_PRODUCT = 'salesforce_order/opportunity_cart/shipping_product_pricebook';
    const ORDER_UPDATE_SHIPPING_TOTAL = 'salesforce_order/opportunity_cart/update_shipping_total';

    const ORDER_USE_DISCOUNT_PRODUCT = 'salesforce_order/opportunity_cart/use_discount_product';
    const ORDER_DISCOUNT_PRODUCT = 'salesforce_order/opportunity_cart/discount_product_pricebook';
    const ORDER_UPDATE_DISCOUNT_TOTAL = 'salesforce_order/opportunity_cart/update_discount_total';

    /* Customer Config */
    const CUSTOMER_CREATE_AS_LEAD = 'salesforce_customer/lead_config/customer_integration';
    const CUSTOMER_SYNC = 'salesforce_customer/sync/customer_sync_enable';
    const CUSTOMER_ALL_GROUPS = 'salesforce_customer/sync/customer_groups_all';
    const CUSTOMER_GROUPS = 'salesforce_customer/sync/customer_groups';
    const DEFAULT_ENTITY_OWNER = 'salesforce_customer/sync/default_owner';

    /* Contact Us Config */
    const CUSTOMER_INTEGRATION_FORM = 'salesforce_contactus/general/customer_form_enable';
    const CUSTOMER_TASK_ASSIGNEE = 'salesforce_contactus/general/customer_form_assigned';

    /* Contacts & Accounts */
    const CUSTOMER_DEFAULT_ACCOUNT = 'salesforce_customer/contact/customer_account'; // Deprecated
    const CUSTOMER_FORCE_RECORDTYPE = 'salesforce_customer/contact/customer_single_record_type';
    const BUSINESS_RECORD_TYPE = 'salesforce_customer/contact/customer_account';
    const CUSTOMER_PERSON_ACCOUNT = 'salesforce_customer/contact/customer_person';
    const PERSON_RECORD_TYPE = 'salesforce_customer/contact/customer_person_account';

    /* Leads */
    const LEAD_CONVERTED_STATUS = 'salesforce_customer/lead_config/customer_lead_status';
    const LEAD_CONVERTED_OWNER = 'salesforce_customer/lead_config/customer_lead_owner';
    const LEAD_SOURCE = 'salesforce_customer/lead_config/lead_source';
    const USE_LEAD_SOURCE_FILTER = 'salesforce_customer/lead_config/use_lead_source_filter';
    const CUSTOMER_RULE = 'salesforce_customer/lead_config/lead_rule';

    /* Existing Constants */
    const CATALOG_PRICE_SCOPE = 'catalog/price/scope';
    const CUSTOMER_ACCOUNT_SHARING = 'customer/account_share/scope';

    const SALESFORCE_ENTERPRISE   = 'Enterprise Edition';
    const SALESFORCE_PROFESSIONAL = 'Professional Edition';
    const SALESFORCE_DEVELOPER    = 'Developer Edition';

    /* Defaults */
    protected $_types = array("Enterprise", "Partner");
    protected $_clientTypes = NULL;
    protected $_pricebooks = array(0 => "Select Pricebook");
    protected $_pricebookTypes = NULL;
    protected $_customerRoles = array();
    protected $_customerRoleTypes = NULL;
    protected $_customerGroups = NULL;
    protected $_leadStatus = array();
    protected $_personAccountRecordTypes = array();
    protected $_businessAccountRecordTypes = array();
    protected $_leadStates = array();

    /**
     * @comment this variable contains parameter name used in SalesForce
     * @var null
     */
    protected $_magentoId = NULL;

    /**
     * package names and versions from wsdl file
     * @var null
     */
    protected $_sfVersions = null;

    /**
     * @return mixed|null|string
     */
    public function getUseLeadSourceFilter()
    {
        return $this->getStoreConfig(self::USE_LEAD_SOURCE_FILTER);
    }

    /**
     * alias for getUseLeadSourceFilter
     * @return mixed|null|string
     */
    public function useLeadSourceFilter()
    {
        return $this->getUseLeadSourceFilter();
    }

    /**
     * @return mixed|null|string
     */
    public function getLeadSource()
    {
        return $this->getStoreConfig(self::LEAD_SOURCE);
    }

    /* Getters */
    public function isMultiCurrency()
    {
        return $this->getStoreConfig(self::ORDER_MULTI_CURRENCY);
    }

    public function getPriceScope()
    {
        return $this->getStoreConfig(self::CATALOG_PRICE_SCOPE);
    }

    /**
     * Extension Type: Enterprise or Professional
     * @deprecated
     * @return string
     */
    final public function getType()
    {
        return 'PRO';
    }

    /**
     * @deprecated
     * @return bool
     */
    final public function isProfessionalEdition()
    {
        return true;
    }

    // License Email
    public function getLicenseEmail()
    {
        return $this->getStoreConfig(self::API_LICENSE_EMAIL);
    }

    // License PayPal invoice ID
    public function getLicenseInvoice()
    {
        return $this->getStoreConfig(self::API_LICENSE_INVOICE);
    }

    // License PayPal transaction ID
    public function getLicenseTransaction()
    {
        return $this->getStoreConfig(self::API_LICENSE_TRANSACTION);
    }

    // Is extension enabled in config

    public function getApiUsername($_currentStoreId = null, $_currentWebsite = null)
    {
        return $this->getStoreConfig(self::API_USERNAME, $_currentStoreId, $_currentWebsite);
    }

    // Salesforce API Username

    public function getApiPassword($_currentStoreId = null, $_currentWebsite = null)
    {
        return $this->getStoreConfig(self::API_PASSWORD, $_currentStoreId, $_currentWebsite);
    }

    // Salesforce API Password

    public function getApiToken($_currentStoreId = null, $_currentWebsite = null)
    {
        return $this->getStoreConfig(self::API_TOKEN, $_currentStoreId, $_currentWebsite);
    }

    // Salesforce API User Tocken

    public function getApiWSDL($_currentStoreId = null, $_currentWebsite = null)
    {
        return $this->getStoreConfig(self::API_WSDL, $_currentStoreId, $_currentWebsite);
    }

    /**
     * @return string
     * @deprecated
     */
    public function getOrderObject()
    {
        switch (Mage::helper('tnw_salesforce/config_sales')->integrationOption()) {
            case TNW_Salesforce_Model_System_Config_Source_Order_Integration_Option::ORDER:
                return TNW_Salesforce_Model_Config_Objects::ORDER_OBJECT;

            case TNW_Salesforce_Model_System_Config_Source_Order_Integration_Option::OPPORTUNITY:
                return TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT;

            default:
                return TNW_Salesforce_Model_Config_Objects::ORDER_OBJECT;
        }
    }

    // Salesforce object where Magento orders will go to

    /**
     * @return string
     * @deprecated
     */
    public function getAbandonedObject()
    {
        return TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT;
    }
    // Salesforce object where Magento orders will go to

    /**
     * Get Invoice Object
     *
     * @return string
     * @deprecated
     */
    public function getInvoiceObject()
    {
        // Allow Powersync to overwite fired event for customizations
        $object = new Varien_Object(array(
            'object_type' => TNW_Salesforce_Model_Order_Invoice_Observer::OBJECT_TYPE
        ));
        Mage::dispatchEvent('tnw_salesforce_invoice_set_object', array('sf_object' => $object));

        return $object->getObjectType();
    }

    /**
     * Get Shipment Object
     *
     * @return string
     * @deprecated
     */
    public function getShipmentObject()
    {
        // Allow Powersync to overwite fired event for customizations
        $object = new Varien_Object(array(
            'object_type' => TNW_Salesforce_Model_Order_Shipment_Observer::OBJECT_TYPE
        ));

        Mage::dispatchEvent('tnw_salesforce_shipment_set_object', array('sf_object' => $object));
        return $object->getData('object_type');
    }

    /**
     * Get Shipment Object
     *
     * @return string
     * @deprecated
     */
    public function getCreditmemoObject()
    {
        // Allow Powersync to overwite fired event for customizations
        $object = new Varien_Object(array(
            'object_type' => TNW_Salesforce_Model_Order_Creditmemo_Observer::OBJECT_TYPE
        ));

        Mage::dispatchEvent('tnw_salesforce_creditmemo_set_object', array('sf_object' => $object));
        return $object->getData('object_type');
    }

    public function isLogEnabled()
    {
        return $this->getStoreConfig(self::API_LOG);
    }

    // Is debug log enabled

    public function getFailEmail()
    {
        return $this->getStoreConfig(self::FAIL_EMAIL);
    }

    // Integration debug email where to send errors

    public function getFailEmailPrefix()
    {
        return $this->getStoreConfig(self::FAIL_EMAIL_SUBJECT);
    }

    /**
     * Integration debug email subject prefix
     * @deprecated
     * @return bool
     */
    public function isRemoteLogEnabled()
    {
        return false;
    }

    // Push data to idealdata.io for debugging

    public function isEnabledOrderSync()
    {
        return $this->getStoreConfig(self::ORDER_SYNC);
    }

    public function isOrderCreateReverseSync()
    {
        return $this->getStoreConfig(self::ORDER_CREATE_REVERSE_SYNC);
    }

    public function getOrderCreateReverseSyncPayment()
    {
        return $this->getStoreConfig(self::ORDER_CREATE_REVERSE_SYNC_PAYMENT);
    }

    public function getOrderCreateReverseSyncShipping()
    {
        return $this->getStoreConfig(self::ORDER_CREATE_REVERSE_SYNC_SHIPPING);
    }

    // Is order synchronization enabled

    public function doPushShoppingCart()
    {
        return $this->getStoreConfig(self::ORDER_PRODUCT_SYNC);
    }

    // Attach Opportunity Line items

    public function isEnabledCustomerRole()
    {
        return $this->getStoreConfig(self::CUSTOMER_ROLE_ENABLED);
    }

    // is Customer Opportunity Role Enabled

    public function getDefaultCustomerRole()
    {
        return $this->getStoreConfig(self::CUSTOMER_ROLE);
    }

    /**
     * @return bool
     */
    public function isRealTimeType()
    {
        return $this->getObjectSyncType() == 'sync_type_realtime';
    }

    /**
     * @return int
     */
    public function getRealTimeSyncMaxCount()
    {
        return $this->getStoreConfig(self::REAL_TIME_SYNC_MAX_COUNT);
    }

    /**
     * @return int
     */
    public function getBulkResultMaxAttentions()
    {
        return (int)$this->getStoreConfig(self::BULK_RESULT_MAX_ATTENTIONS);
    }

    // Default Customer Opportunity Role

    public function getObjectSyncType()
    {
        return $this->getStoreConfig(self::OBJECT_SYNC_TYPE);
    }

    // queue object sync type

    public function getCronLastRunTimestamp()
    {
        return $this->getStoreConfig(self::CRON_LAST_RUN_TIMESTAMP);
    }

    // spectime minute

    public function isOrderNotesEnabled()
    {
        return $this->getStoreConfig(self::NONES_SYNC);
    }

    public function isOrderRulesEnabled()
    {
        return $this->getStoreConfig(self::CAMPAIGNS_SYNC);
    }

    public function getSyncOrderRulesButtonData()
    {
        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule  = Mage::registry('current_promo_quote_rule');
        $url   = Mage::getModel('adminhtml/url')->getUrl('*/salesforcesync_campaign_salesrulesync/sync', array('salesrule_id' => $rule->getId()));

        return array(
            'label'   => Mage::helper('tnw_salesforce')->__('Synchronize w/ Salesforce'),
            'onclick' => "setLocation('$url')",
        );
    }

    public function isCampaignsCreateAutomate()
    {
        return $this->getStoreConfig(self::CAMPAIGNS_CREATE_AUTOMATE);
    }

    public function isEnabledProductSync()
    {
        return $this->getStoreConfig(self::PRODUCT_SYNC);
    }

    // Is product synchronization enabled

    public function getDefaultPricebook()
    {
        return $this->getStoreConfig(self::PRODUCT_PRICEBOOK);
    }

    // Salesforce default Pricebook used to store Product prices from Magento

    public function getCustomerNewsletterSync()
    {
        return $this->getStoreConfig(self::CUSTOMER_NEWSLETTER_SYNC);
    }

    public function getCutomerCampaignId()
    {
        return $this->getStoreConfig(self::CUSTOMER_CAMPAIGN_ID);
    }

    public function isEnabledCustomerSync()
    {
        return $this->getStoreConfig(self::CUSTOMER_SYNC);
    }

    // Customer integration type

    public function getSyncAllGroups()
    {
        return $this->getStoreConfig(self::CUSTOMER_ALL_GROUPS);
    }

    // Syn all customer groups by default

    public function isEnabledContactForm()
    {
        return $this->getStoreConfig(self::CUSTOMER_INTEGRATION_FORM);
    }

    // Get list of enabled CustomerGroups

    public function getTaskAssignee()
    {
        return $this->getStoreConfig(self::CUSTOMER_TASK_ASSIGNEE);
    }

    // Customer integration form

    public function isCustomerAsLead()
    {
        return $this->getStoreConfig(self::CUSTOMER_CREATE_AS_LEAD);
    }

    // Customer integration type

    public function usePersonAccount()
    {
        return $this->getStoreConfig(self::CUSTOMER_PERSON_ACCOUNT);
    }

    // Can rename Account in Salesforce

    public function getPersonAccountRecordType()
    {
        return $this->getStoreConfig(self::PERSON_RECORD_TYPE);
    }

    // Customer is Person Account Enabled

    public function getBusinessAccountRecordType()
    {
        return $this->getStoreConfig(self::BUSINESS_RECORD_TYPE);
    }

    // Customer Person Account Record Type

    public function getLeadConvertedStatus()
    {
        $status = $this->getStoreConfig(self::LEAD_CONVERTED_STATUS);

        /**
         * @comment use default status
         */
        if (!$status) {
            $status = 'Closed - Converted';
        }

        return $status;
    }

    // Customer Business Account Record Type

    public function getLeadDefaultOwner($storeId = null, $websiteId = null)
    {
        return $this->getStoreConfig(self::LEAD_CONVERTED_OWNER, $storeId, $websiteId);
    }

    // Customer get Lead Converted Status

    public function getDefaultOwner($storeId = null, $websiteId = null)
    {
        return $this->getStoreConfig(self::DEFAULT_ENTITY_OWNER, $storeId, $websiteId);
    }

    // Default Lead owner to be used during conversion

    /**
     * @return integer
     * @deprecated
     */
    public function isCustomerSingleRecordType()
    {
        return $this->customerTypeRecordType();
    }

    /**
     * @return integer
     */
    public function customerTypeRecordType()
    {
        return $this->getStoreConfig(self::CUSTOMER_FORCE_RECORDTYPE);
    }

    // Default Lead/Contact/Account owner to be used during conversion

    public function isLeadRule()
    {
        return $this->getStoreConfig(self::CUSTOMER_RULE);
    }

    // Check if Customer Record Type is forced to one per website

    public function useTaxFeeProduct()
    {
        return $this->getStoreConfig(self::ORDER_USE_TAX_PRODUCT);
    }

    // Default Assignment rule to use when creating a lead

    public function useShippingFeeProduct()
    {
        return $this->getStoreConfig(self::ORDER_USE_SHIPPING_PRODUCT);
    }

    // Use Tax Fee Product

    public function useDiscountFeeProduct()
    {
        return $this->getStoreConfig(self::ORDER_USE_DISCOUNT_PRODUCT);
    }

    /**
     * @param $feeType
     * @return bool
     */
    public function useFeeByType($feeType)
    {
        switch ($feeType) {
            case 'tax':
                return $this->useTaxFeeProduct();

            case 'discount':
                return $this->useDiscountFeeProduct();

            case 'shipping':
                return $this->useShippingFeeProduct();

            default:
                return false;
        }
    }

    // Use Shipping Fee Product

    public function syncAllOrders()
    {
        return $this->getStoreConfig(self::ORDER_STATUS_ALL);
    }

    // Use Discount Fee Product

    public function getAllowedOrderStates()
    {
        return $this->getStoreConfig(self::ORDER_STATUS_ALLOW);
    }

    /**
     * Can we use order address for customer
     *
     * @return mixed
     */
    public function getOrderCustomerAddress()
    {
        return $this->getStoreConfig(self::ORDER_CUSTOMER_ADDRESS);
    }

    /**
     * Returns true if order address using is allowed
     *
     * @return mixed
     */
    public function canUseOrderAddress()
    {
        return $this->getOrderCustomerAddress();
    }

    // Sync all orders or not

    public function getTaxProduct()
    {
        return $this->getStoreConfig(self::ORDER_TAX_PRODUCT);
    }

    /**
     * @return bool
     */
    public function isUpdateTaxTotal()
    {
        return (bool)(int)$this->getStoreConfig(self::ORDER_UPDATE_TAX_TOTAL);
    }

    // get a list of allowed order states for the sync

    public function getShippingProduct()
    {
        return $this->getStoreConfig(self::ORDER_SHIPPING_PRODUCT);
    }

    /**
     * @return bool
     */
    public function isUpdateShippingTotal()
    {
        return (bool)(int)$this->getStoreConfig(self::ORDER_UPDATE_SHIPPING_TOTAL);
    }

    // Tax Fee Product

    public function getDiscountProduct()
    {
        return $this->getStoreConfig(self::ORDER_DISCOUNT_PRODUCT);
    }

    /**
     * @return bool
     */
    public function isUpdateDiscountTotal()
    {
        return (bool)(int)$this->getStoreConfig(self::ORDER_UPDATE_DISCOUNT_TOTAL);
    }

    /**
     * @param $feeType
     * @return bool
     */
    public function isUpdateTotalByFeeType($feeType)
    {
        switch ($feeType) {
            case 'tax':
                return $this->isUpdateTaxTotal();

            case 'discount':
                return $this->isUpdateDiscountTotal();

            case 'shipping':
                return $this->isUpdateShippingTotal();

            default:
                return false;
        }
    }

    // Shipping Fee Product

    public function getDefaultAccountId()
    {
        return $this->getStoreConfig(self::CUSTOMER_DEFAULT_ACCOUNT);
    }

    /**
     * @return bool
     * @deprecated
     */
    public function createPersonAccount()
    {
        return $this->usePersonAccount();
    }

    // Customer integration type
    // @deprecated

    public function isSoapEnabled()
    {
        return (class_exists('SoapClient')) ? true : false;
    }

    public function isMbstringEnabled()
    {
        return extension_loaded('mbstring');
    }


    // Customer is Person Account Enabled
    // @deprecated

    public function getPricebookId($storeId)
    {
        return $this->_getPresetConfig(self::PRODUCT_PRICEBOOK, $storeId);
    }

    public function getCustomerScope()
    {
        return $this->getStoreConfig(self::CUSTOMER_ACCOUNT_SHARING);
    }

    //Get Preset Config

    public function getMagentoVersion()
    {
        return (int)str_replace(".", "", Mage::getVersion());
    }

    //Get Customer Account

    public function getDate($_time = NULL, $_isTrue = true)
    {
        $_timeStamp = (!$_isTrue) ? $this->getMagentoTime($_time) : $this->getTime($_time);
        return gmdate(DATE_ATOM, $_timeStamp);
    }

    // Magento version

    public function getMagentoTime($_time = NULL)
    {
        if (!$_time) {
            $_time = time();
        }
        return Mage::getModel('core/date')->timestamp($_time);
    }

    public function getTime($_time = NULL) {
        if (!$_time) {
            $_time = time();
        }
        return $_time;
    }

    public function getClientTypes()
    {
        if (!$this->_clientTypes) {
            $this->_clientTypes = array();
            foreach ($this->_types as $_obj) {
                $this->_clientTypes[] = array(
                    'label' => $_obj,
                    'value' => $_obj
                );
            }
        }
        return $this->_clientTypes;
    }

    /**
     * check if our client (mage) is authorized to work with sf
     *
     * @return bool
     */
    public function canPush()
    {
        if ($this->isWorking()) {
            Mage::getSingleton('core/session')->setSfNotWorking(false);
            return true;
        }

        return false;
    }

    // PHP Version

    /**
     * Check if we are allowed to use the extension
     *
     * @return bool
     */
    public function isWorking()
    {
        try {
            if ($this->isEnabled() && $this->checkPhpVersion()
                && Mage::getSingleton('tnw_salesforce/license')->getStatus()
            ) {
                return true;
            }
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: ' . $e->getMessage());
        }

        return false;
    }

    // Salesforce org type: Partner or Enterprise

    public function isEnabled()
    {
        return $this->getStoreConfig(self::API_ENABLED);
    }

    /**
     * @return bool
     */
    public function checkPhpVersion()
    {
        return version_compare(PHP_VERSION, '5.4.0', '>=');
    }

    /**
     * get salesforce lead states
     *
     * @return array
     */
    public function getLeadStates()
    {
        $this->_leadStatus = $this->getStorage('tnw_salesforce_lead_states');
        if (empty($this->_leadStatus)) {
            if ($collection = Mage::helper('tnw_salesforce/salesforce_data')->getStatus()) {
                foreach ($collection as $_item) {
                    $this->_leadStates[$_item->Id] = $_item->MasterLabel;
                }
                unset($collection, $_item);
            }

            $this->_leadStatus = array();
            foreach ($this->_leadStates as $key => $_obj) {
                $this->_leadStatus[] = array(
                    'label' => $_obj,
                    'value' => $_obj
                );
            }

            $this->setStorage($this->_leadStatus, 'tnw_salesforce_lead_states');
        }

        return $this->_leadStatus;
    }

    public function getPersonAccountRecordIds()
    {
        $this->_personAccountRecordTypes = $this->getStorage('tnw_salesforce_person_account_record_types');
        if (empty($this->_personAccountRecordTypes)) {
            if ($collection = Mage::helper('tnw_salesforce/salesforce_data')->getAccountPersonRecordType()) {
                foreach ($collection as $_item) {
                    $this->_personAccountRecordTypes[$_item->Id] = $_item->Name;
                }
                unset($collection, $_item);
            }

            $this->setStorage($this->_personAccountRecordTypes, 'tnw_salesforce_person_account_record_types');
        }

        return $this->_personAccountRecordTypes;
    }

    // Get Salesforce Person Account Record Id

    public function getBusinessAccountRecordIds()
    {
        $this->_businessAccountRecordTypes = $this->getStorage('tnw_salesforce_business_account_record_types');
        if (empty($this->_businessAccountRecordTypes)) {
            if ($collection = Mage::helper('tnw_salesforce/salesforce_data')->getAccountBusinessRecordType()) {
                foreach ($collection as $_item) {
                    $this->_businessAccountRecordTypes[$_item->Id] = $_item->Name;
                }
                unset($collection, $_item);
            }

            $this->setStorage($this->_businessAccountRecordTypes, 'tnw_salesforce_business_account_record_types');
        }

        return $this->_businessAccountRecordTypes;
    }

    // Get Salesforce Business Account Record Id

    public function getPriceBooks()
    {
        $this->_pricebookTypes = $this->getStorage('tnw_salesforce_pricebooks');
        if (empty($this->_pricebookTypes)) {
            if ($collection = Mage::helper('tnw_salesforce/salesforce_data')->getNotStandardPricebooks()) {
                foreach ($collection as $id => $name) {
                    $this->_pricebooks[$id] = $name;
                }
                unset($collection, $id, $name);
            }

            $this->_pricebookTypes = array();
            foreach ($this->_pricebooks as $key => $_obj) {
                $this->_pricebookTypes[] = array(
                    'label' => $_obj,
                    'value' => $key
                );
            }

            $this->setStorage($this->_pricebookTypes, 'tnw_salesforce_pricebooks');
        }

        return $this->_pricebookTypes;
    }

    // Get Salesforce Pricebooks

    public function getCustomerRoles()
    {
        $this->_customerRoleTypes = $this->getStorage("tnw_salesforce_opportunity_customer_roles");
        if (empty($this->_customerRoleTypes)) {
            $collection = Mage::helper('tnw_salesforce/salesforce_data')->getPicklistValues('OpportunityContactRole', 'Role');
            if ($collection) {
                foreach ($collection as $_role) {
                    if ($_role->active) {
                        $this->_customerRoles[$_role->value] = $_role->label;
                    }
                }
                unset($collection, $role);
            }

            $this->_customerRoleTypes = array();
            foreach ($this->_customerRoles as $key => $_obj) {
                $this->_customerRoleTypes[] = array(
                    'label' => $_obj,
                    'value' => $key
                );
            }

            $this->setStorage($this->_customerRoleTypes, 'tnw_salesforce_opportunity_customer_roles');
        }

        return $this->_customerRoleTypes;
    }

    // Get Salesforce Opportunity Customer Roles

    public function getCustomerGroups()
    {
        if (!$this->_customerGroups) {
            $collection = Mage::getModel('customer/group')->getCollection();
            $this->_customerGroups = array();
            foreach ($collection as $key => $_obj) {
                $this->_customerGroups[] = array(
                    'label' => $_obj->getCustomerGroupCode(),
                    'value' => $_obj->getCustomerGroupId()
                );
            }
        }
        return $this->_customerGroups;
    }

    // Get Salesforce Opportunity Customer Roles

    /**
     * @return bool
     */
    public function displayErrors()
    {
        return Mage::getSingleton('admin/session')->isLoggedIn();
    }

    /**
     * @return Mage_Core_Model_Config_Element
     */
    public function getExtensionVersion()
    {
        return Mage::getConfig()->getNode('modules/TNW_Salesforce/version');
    }

    /**
     * post extension version in the footer
     *
     * @param $module
     * @return $this
     */
    public function addAdminhtmlVersion($module)
    {
        $layout = Mage::app()->getLayout();
        $version = (string)Mage::getConfig()
            ->getNode("modules/{$module}/version");

        $layout->getBlock('before_body_end')->append(
            $layout->createBlock('core/text')->setText('
 				<script type="text/javascript">
					$$(".legality")[0].insert({after:"' . $module . ' ver. ' . $version . '<br/>"});
				</script>
        	')
        );
        unset($module, $version);

        return $this;
    }

    /**
     * @param null $groupId
     * @return bool
     */
    public function syncCustomer($groupId = NULL)
    {
        if (in_array($groupId, $this->getAllowedCustomerGroups())) {
            return true;
        }

        return false;
    }

    public function getAllowedCustomerGroups()
    {
        return explode(',', $this->getStoreConfig(self::CUSTOMER_GROUPS));
    }

    /**
     * @return array
     * @deprecated
     */
    public function getCatchAllAccounts()
    {
        $newArray = array(
            'domain'  => array(),
            'account' => array(),
        );

        /** @var TNW_Salesforce_Model_Mysql4_Account_Matching_Collection $collection */
        $collection = Mage::getModel('tnw_salesforce/account_matching')
            ->getCollection();

        /** @var TNW_Salesforce_Model_Account_Matching $item */
        foreach ($collection as $item) {
            $newArray['domain'][]  = $item->getData('email_domain');
            $newArray['account'][] = $item->getData('account_id');
        }

        return $newArray;
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        if (Mage::app()->getStore()->isAdmin()) {
            return true;
        }

        if (Mage::getDesign()->getArea() == 'adminhtml') {
            return true;
        }

        return false;
    }

    /**
     * Check if we currently on Api Configuration page
     *
     * @return bool
     */
    public function isApiConfigurationPage()
    {
        return $this->_getRequest()->getRouteName() == 'adminhtml'
            && $this->_getRequest()->getControllerName() == 'system_config'
            && $this->_getRequest()->getActionName() == 'edit'
            && $this->_getRequest()->getParam('section') == 'salesforce';
    }

    /**
     * @return bool
     */
    public function isLoginPage()
    {
        return $this->_getRequest()->getModuleName() == 'admin'
            && $this->_getRequest()->getControllerName() == 'index'
            && $this->_getRequest()->getActionName() == 'login';
    }

    /**
     * @comment returns const
     * @param $feeType
     * @return mixed|null
     * @throws Mage_Core_Exception
     */
    public function getFeeProduct($feeType)
    {
        /**
         * @var $_constantName string ORDER_DISCOUNT_PRODUCT|ORDER_SHIPPING_PRODUCT|ORDER_TAX_PRODUCT
         */
        $_constantName = 'self::ORDER_'.strtoupper($feeType).'_PRODUCT';

        if (defined($_constantName)) {
            return constant($_constantName);
        }

        Mage::throwException('Undefined fee product: for ' . $feeType);

        return NULL;
    }

    /**
     * load package names and versions from wsdl file
     * @return string
     */
    public function getSalesforcePackagesVersion()
    {
        $_model = TNW_Salesforce_Model_Connection::createConnection();
        $_model->tryWsdl();

        if (!$this->_sfVersions && $_model->isWsdlFound()) {
            $wsdlFile = $_model->getWsdl();

            $wsdl = file_get_contents($wsdlFile);

            $doc = new DOMDocument;
            $doc->loadXML($wsdl);
            $xpath = new DOMXPath($doc);

            foreach ($xpath->query('//comment()') as $comment) {
                if (strpos($comment->textContent, 'Package Versions:') !== false) {
                    $this->_sfVersions = $comment->textContent;
                    break;
                }
            }

        }

        return $this->_sfVersions;
    }

    /**
     * try to find info about Salesforce version type: Enterprise or Professional
     *
     * @return string
     */
    public function getSalesforceVersionType()
    {
        $type = self::SALESFORCE_ENTERPRISE;

        try {
            /** @var stdClass $typeObj */
            $typeObj = $this->getClient()->query('select OrganizationType from Organization');
            if (!empty($typeObj->records)) {
                $type = $typeObj->records[0]->OrganizationType;
            }
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: ' . $e->getMessage());
        }

        return $type;
    }

    /**
     * Is Salesforce Enterprise version?
     * @return bool
     */
    public function isProfessionalSalesforceVersionType()
    {
        return $this->getSalesforceVersionType() == self::SALESFORCE_PROFESSIONAL;
    }

}