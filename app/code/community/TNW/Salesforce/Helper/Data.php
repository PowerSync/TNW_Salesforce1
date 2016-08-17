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
    const REMOTE_LOG = 'salesforce/development_and_debugging/remote_log';

    /* Product */
    const PRODUCT_SYNC = 'salesforce_product/general/product_enable';
    const PRODUCT_PRICEBOOK = 'salesforce_product/general/default_pricebook';

    // newsletter related
    const CUSTOMER_NEWSLETTER_SYNC = 'salesforce_customer/newsletter_config/customer_newsletter_enable_sync';
    const CUSTOMER_CAMPAIGN_ID = 'salesforce_customer/newsletter_config/customer_newletter_campaign_id';

    /* Order Config */
    const ORDER_SYNC = 'salesforce_order/general/order_sync_enable';
    const ORDER_PRODUCT_SYNC = 'salesforce_order/general/order_product_enable';
    const ORDER_MULTI_CURRENCY = 'salesforce_order/currency/multi_currency';
    const ORDER_STATUS_ALL = 'salesforce_order/general/order_status_all';
    const ORDER_STATUS_ALLOW = 'salesforce_order/general/order_status_allow';
    const ORDER_CUSTOMER_ADDRESS = 'salesforce_order/general/customer_address';

    // Notes Config
    const NONES_SYNC = 'salesforce_order/general/notes_synchronize';

    // Campaigns Config
    const CAMPAIGNS_SYNC = 'salesforce_promotions/salesforce_campaigns/sync_enabled';
    const CAMPAIGNS_CREATE_AUTOMATE = 'salesforce_promotions/salesforce_campaigns/create_campaign_automatic';

    /* Order Customer Role */
    const ORDER_OBJECT = 'salesforce_order/customer_opportunity/order_or_opportunity';
    const CUSTOMER_ROLE_ENABLED = 'salesforce_order/customer_opportunity/customer_opportunity_role_enable';
    const CUSTOMER_ROLE = 'salesforce_order/customer_opportunity/customer_integration_opp';

    /* queue object sync settings */
    const OBJECT_SYNC_TYPE = 'salesforce/syncronization/sync_type_realtime';
    const OBJECT_SYNC_INTERVAL_VALUE = 'salesforce/syncronization/sync_type_queueinterval_value';
    const OBJECT_SYNC_SPECTIME = 'salesforce/syncronization/sync_type_spectime';
    const OBJECT_SYNC_SPECTIME_FREQUENCY_WEEKLY = 'salesforce/syncronization/sync_type_spectime_frequency_weekly';
    const OBJECT_SYNC_SPECTIME_FREQUENCY_MONTH_DAY = 'salesforce/syncronization/sync_type_spectime_month_day';
    const OBJECT_SYNC_SPECTIME_FREQ = 'salesforce/syncronization/sync_type_spectime_frequency';
    const OBJECT_SYNC_SPECTIME_HOUR = 'salesforce/syncronization/sync_type_spectime_hour';
    const OBJECT_SYNC_SPECTIME_MINUTE = 'salesforce/syncronization/sync_type_spectime_minute';

    // last cron run time
    const CRON_LAST_RUN_TIMESTAMP = 'salesforce/syncronization/cron_last_run_timestamp';

    /* Order Use Opportunity Products */
    //const ORDER_USE_PRODUCTS = 'salesforce_order/opportunity_cart/use_products';
    const ORDER_USE_TAX_PRODUCT = 'salesforce_order/opportunity_cart/use_tax_product';
    const ORDER_TAX_PRODUCT = 'salesforce_order/opportunity_cart/tax_product_pricebook';

    const ORDER_USE_SHIPPING_PRODUCT = 'salesforce_order/opportunity_cart/use_shipping_product';
    const ORDER_SHIPPING_PRODUCT = 'salesforce_order/opportunity_cart/shipping_product_pricebook';

    const ORDER_USE_DISCOUNT_PRODUCT = 'salesforce_order/opportunity_cart/use_discount_product';
    const ORDER_DISCOUNT_PRODUCT = 'salesforce_order/opportunity_cart/discount_product_pricebook';

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
    const MODULE_TYPE = 'PRO';
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

    //const MODULE_TYPE = 'BASIC';
    /**
     * @comment this variable contains parameter name used in SalesForce
     * @var null
     */
    protected $_magentoId = NULL;
    /**
     * sync frequency
     *
     * @var array
     */
    protected $_syncFrequency = array(
        'Daily' => 86400, // 60 * 60 * 24
        'Weekly' => 604800, // 60 * 60 * 24 * 7
        'Monthly' => 2592000, // 30 days = 60 * 60 * 24 * 30
    );

    /**
     * sync frequency week list
     *
     * @var array
     */
    protected $_syncFrequencyWeekList = array(
        'Monday' => 'Monday',
        'Tuesday' => 'Tuesday',
        'Wednesday' => 'Wednesday',
        'Thursday' => 'Thursday',
        'Friday' => 'Friday',
        'Saturday' => 'Saturday',
        'Sunday' => 'Sunday',
    );

    /**
     * cron run interval
     *
     * @var array
     */
    protected $_queueSyncInterval = array(
        '5 minutes' => 300,
        '15 minutes' => 900,
        '30 minutes' => 1800,
        '1 hour' => 3600,
        '3 hours' => 10800,
        '6 hours' => 21600,
        '12 hours' => 43200,
    );

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

    //Extension Type: Enterprise or Professional
    final public function getType()
    {
        return self::MODULE_TYPE;
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

    public function getApiUsername()
    {
        return $this->getStoreConfig(self::API_USERNAME);
    }

    // Salesforce API Username

    public function getApiPassword()
    {
        return $this->getStoreConfig(self::API_PASSWORD);
    }

    // Salesforce API Password

    public function getApiToken()
    {
        return $this->getStoreConfig(self::API_TOKEN);
    }

    // Salesforce API User Tocken

    public function getApiWSDL()
    {
        return $this->getStoreConfig(self::API_WSDL);
    }

    // Salesforce WSDL file location

    public function getOrderObject()
    {
        return $this->getStoreConfig(self::ORDER_OBJECT);
    }

    // Salesforce object where Magento orders will go to

    public function getAbandonedObject()
    {
        return TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT;
    }
    // Salesforce object where Magento orders will go to

    /**
     * Get Invoice Object
     *
     * @return string
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

    // Integration debug email subject prefix

    public function isRemoteLogEnabled()
    {
        return $this->getStoreConfig(self::REMOTE_LOG);
    }

    // Push data to idealdata.io for debugging

    public function isEnabledOrderSync()
    {
        return $this->getStoreConfig(self::ORDER_SYNC);
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

    // cron run last time

    public function getObjectSyncSpectimeFreq()
    {
        return $this->getStoreConfig(self::OBJECT_SYNC_SPECTIME_FREQ);
    }

    // object sync spec time frequency

    public function getObjectSyncSpectimeFreqWeekday()
    {
        return $this->getStoreConfig(self::OBJECT_SYNC_SPECTIME_FREQUENCY_WEEKLY);
    }

    // get sync day of week

    public function getObjectSyncSpectimeFreqMonthday()
    {
        return $this->getStoreConfig(self::OBJECT_SYNC_SPECTIME_FREQUENCY_MONTH_DAY);
    }

    // get sync day of month

    public function getObjectSyncIntervalValue()
    {
        return $this->getStoreConfig(self::OBJECT_SYNC_INTERVAL_VALUE);
    }

    public function getOrderSyncPeriod()
    {
        return $this->getStoreConfig(self::ORDER_SYNC_INTERVAL);
    }

    // order sync period

    public function getObjectSpectimeHour()
    {
        return $this->getStoreConfig(self::OBJECT_SYNC_SPECTIME_HOUR);
    }

    // spectime hour

    public function getObjectSpectimeMinute()
    {
        return $this->getStoreConfig(self::OBJECT_SYNC_SPECTIME_MINUTE);
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

    public function getLeadDefaultOwner()
    {
        return $this->getStoreConfig(self::LEAD_CONVERTED_OWNER);
    }

    // Customer get Lead Converted Status

    public function getDefaultOwner()
    {
        return $this->getStoreConfig(self::DEFAULT_ENTITY_OWNER);
    }

    // Default Lead owner to be used during conversion

    public function isCustomerSingleRecordType()
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

    // get a list of allowed order states for the sync

    public function getShippingProduct()
    {
        return $this->getStoreConfig(self::ORDER_SHIPPING_PRODUCT);
    }

    // Tax Fee Product

    public function getDiscountProduct()
    {
        return $this->getStoreConfig(self::ORDER_DISCOUNT_PRODUCT);
    }

    // Shipping Fee Product

    public function getDefaultAccountId()
    {
        return $this->getStoreConfig(self::CUSTOMER_DEFAULT_ACCOUNT);
    }

    // Discount Fee Product

    public function createPersonAccount()
    {
        return $this->getStoreConfig(self::CUSTOMER_PERSON_ACCOUNT);
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
        if ($this->isWorking()
            && Mage::getSingleton('tnw_salesforce/connection')->getClient()
        ) {
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

    public function checkPhpVersion()
    {
        if (!defined('PHP_VERSION_ID')) {
            $version = explode('.', PHP_VERSION);
            define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
            unset($version);
        }

        if (PHP_VERSION_ID > 50300) {
            return true;
        }
        return false;
    }

    /**
     * get salesforce lead states
     *
     * @return array
     */
    public function getLeadStates()
    {
        if ($this->isWorking()) {
            if ($collection = Mage::helper('tnw_salesforce/salesforce_data')->getStatus()) {
                foreach ($collection as $_item) {
                    $this->_leadStates[$_item->Id] = $_item->MasterLabel;
                }
                unset($collection, $_item);
            }
        }
        if (!$this->_leadStatus) {
            $this->_leadStatus = array();
            foreach ($this->_leadStates as $key => $_obj) {
                $this->_leadStatus[] = array(
                    'label' => $_obj,
                    'value' => $_obj
                );
            }
        }

        return $this->_leadStatus;
    }

    /**
     * return list of quote statuses in salesforce
     *
     * @return array
     */
    public function quoteStatusDropdown()
    {
        $collection = array();
        //Only look for Quote status data if Quote integration is enabled
        if ($this->getDefaultQuoteEnableSettings()) {
            $collection = Mage::helper('tnw_salesforce/salesforce_data')->getPicklistValues('Quote', 'Status');
        }

        $res = array();
        foreach ($collection as $item) {
            $res[] = array(
                'label' => $item->label,
                'value' => $item->value,
            );
        }

        return $res;
    }

    /**
     * sync interval list
     *
     * @return array
     */
    public function queueInterval()
    {
        $res = array();
        foreach ($this->_queueSyncInterval as $key => $value) {
            $res[] = array(
                'label' => $key,
                'value' => $value,
            );
        }

        return $res;
    }

    /**
     * sync frequency
     *
     * @return array
     */
    public function syncFrequency()
    {
        $res = array();
        foreach ($this->_syncFrequency as $key => $value) {
            $res[] = array(
                'label' => $key,
                'value' => $value,
            );
        }

        return $res;
    }

    /**
     * sync frequency weeklist
     *
     * @return array
     */
    public function _syncFrequencyWeekList()
    {
        $res = array();
        foreach ($this->_syncFrequencyWeekList as $key => $value) {
            $res[] = array(
                'label' => $key,
                'value' => $value,
            );
        }

        return $res;
    }

    /**
     * day list
     *
     * @return array
     */
    public function _syncFrequencyDayList()
    {
        $res = array();
        for ($i = 1; $i <= 31; $i++) {
            $res[] = array(
                'label' => $i,
                'value' => $i,
            );
        }

        return $res;
    }

    /**
     * sync time minute list
     *
     * @return array
     */
    public function syncTimeminute()
    {
        $res = array();
        for ($i = 0; $i <= 55; $i += 5) {
            $res[] = array(
                'label' => "$i minute",
                'value' => $i,
            );
        }

        return $res;
    }

    /**
     * sync time hour list
     *
     * @return array
     */
    public function syncTimehour()
    {
        $res = array();
        for ($i = 0; $i <= 23; $i++) {
            $res[] = array(
                'label' => "$i hour",
                'value' => $i,
            );
        }

        return $res;
    }

    /**
     * queue sync interval
     *
     * @return array
     */
    public function queueSyncIntervalDropdown()
    {
        $res = array();
        foreach ($this->_queueSyncInterval as $key => $value) {
            $res[] = array(
                'label' => $key,
                'value' => $value,
            );
        }

        return $res;
    }

    public function getPersonAccountRecordIds()
    {
        if ($this->isWorking()) {
            if ($collection = Mage::helper('tnw_salesforce/salesforce_data')->getAccountPersonRecordType()) {
                foreach ($collection as $_item) {
                    $this->_personAccountRecordTypes[$_item->Id] = $_item->Name;
                }
                unset($collection, $_item);
            }
        }
        if (!$this->_personAccountRecordTypes) {
            $this->_personAccountRecordTypes = array();
            foreach ($this->_personAccountRecordTypes as $key => $_obj) {
                $this->_personAccountRecordTypes[] = array(
                    'label' => $_obj,
                    'value' => $_obj
                );
            }
        }
        return $this->_personAccountRecordTypes;
    }

    // Get Salesforce Person Account Record Id

    public function getBusinessAccountRecordIds()
    {
        if ($this->isWorking()) {
            if ($collection = Mage::helper('tnw_salesforce/salesforce_data')->getAccountBusinessRecordType()) {
                foreach ($collection as $_item) {
                    $this->_businessAccountRecordTypes[$_item->Id] = $_item->Name;
                }
                unset($collection, $_item);
            }
        }
        if (!$this->_businessAccountRecordTypes) {
            $this->_businessAccountRecordTypes = array();
            foreach ($this->_businessAccountRecordTypes as $key => $_obj) {
                $this->_businessAccountRecordTypes[] = array(
                    'label' => $_obj,
                    'value' => $_obj
                );
            }
        }
        return $this->_businessAccountRecordTypes;
    }

    // Get Salesforce Business Account Record Id

    public function getPriceBooks()
    {
        if ($this->isWorking()) {
            if ($collection = Mage::helper('tnw_salesforce/salesforce_data')->getNotStandardPricebooks()) {
                foreach ($collection as $id => $name) {
                    $this->_pricebooks[$id] = $name;
                }
                unset($collection, $id, $name);
            }
        }
        if (!$this->_pricebookTypes) {
            $this->_pricebookTypes = array();
            foreach ($this->_pricebooks as $key => $_obj) {
                $this->_pricebookTypes[] = array(
                    'label' => $_obj,
                    'value' => $key
                );
            }
        }
        return $this->_pricebookTypes;
    }

    // Get Salesforce Pricebooks

    public function getCustomerRoles()
    {
        if ($this->isWorking() && $this->isEnabled()) {
            $collection = Mage::helper('tnw_salesforce/salesforce_data')->getPicklistValues('OpportunityContactRole', 'Role');
            if ($collection) {
                foreach ($collection as $_role) {
                    if ($_role->active) {
                        $this->_customerRoles[$_role->value] = $_role->label;
                    }
                }
                unset($collection, $role);
            }
        }
        $this->_customerRoleTypes = array();
        foreach ($this->_customerRoles as $key => $_obj) {
            $this->_customerRoleTypes[] = array(
                'label' => $_obj,
                'value' => $key
            );
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
        return ($this->getWebsiteId() == 0) ? true : false;
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
        $_model = Mage::getSingleton('tnw_salesforce/connection');
        $_model->tryWsdl();

        if (!$this->_sfVersions && $_model->isWsdlFound()) {
            $sfClient = Mage::getSingleton('tnw_salesforce/connection');
            $wsdlFile = $sfClient->getWsdl();

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
            $this->checkConnection();

            if ($this->_mySforceConnection) {
                $typeObj = $this->_mySforceConnection->query('select OrganizationType from Organization');
                if (is_object($typeObj) && property_exists($typeObj, 'size') && $typeObj->size) {
                    $type = $typeObj->records[0]->OrganizationType;
                }
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