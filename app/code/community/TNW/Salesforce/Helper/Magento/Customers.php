<?php

/**
 * Class TNW_Salesforce_Helper_Magento_Customers
 */
class TNW_Salesforce_Helper_Magento_Customers extends TNW_Salesforce_Helper_Magento_Abstract
{
    /**
     * @var null
     */
    protected $_attributes = NULL;

    /**
     * @var null
     */
    protected $_customer = NULL;

    /**
     * @var array
     */
    protected $_mapCollection = array();

    /**
     * @var bool
     */
    protected $_isNew = false;

    /**
     * @var bool
     */
    protected $_isPersonAccount = false;

    /**
     * @var null
     */
    protected $_salesforceObject = NULL;

    /**
     * @var null
     */
    protected $_email = NULL;

    /**
     * @var null
     */
    protected $_accountId = NULL;

    /**
     * @var null
     */
    protected $_salesforceId = NULL;

    /**
     * @var null
     */
    protected $_groupId = NULL;

    /**
     * @var null
     */
    protected $_regionCode = NULL;

    /**
     * @var null
     */
    protected $_countryCode = NULL;

    /**
     * @var null
     */
    protected $_magentoId = null;

    protected $_skip = false;

    /**
     * @var null
     */
    protected $_websiteId = NULL;

    public function __construct()
    {
        parent::__construct();
        //$this->prepare();
    }

    /**
     * @param null $_object
     * @return bool|false|Mage_Core_Model_Abstract
     */
    public function process($_object = null)
    {
        if (
            !$_object
            || !Mage::helper('tnw_salesforce')->isWorking()
        ) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("No Salesforce object passed on connector is not working");
            return false;
        }
        $this->_response = new stdClass();

        $this->_salesforceObject = $_object;
        unset($_object);

        $this->_prepare();

        $_type = $this->_salesforceObject->attributes->type;
        unset($this->_salesforceObject->attributes);
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("** " . $_type . " #" . $this->_salesforceObject->Id . " **");

        $_entity = $this->syncFromSalesforce();

        if (!$this->_skip) {
            // Update history orders and assigne to customer we just created
            $this->_assignCustomerToOrder($_entity->getData('email'), $_entity->getId());

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("** finished upserting " . $_type . " #" . $this->_salesforceObject->Id . " **");

            // Handle success and fail
            if (is_object($_entity)) {
                $this->_response->success = true;
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Salesforce " . $_type . " #" . $this->_salesforceObject->Id . " upserted!");
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Magento Id: " . $_entity->getId());
            } else {
                $this->_response->success = false;
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not upsert " . $_type . " into Magento, see Magento log for details");
                $_entity = false;
            }

            if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
                $logger = Mage::helper('tnw_salesforce/report');
                $logger->reset();

                $logger->add('Magento', 'Customer', array($this->_salesforceObject->Id => $this->_salesforceObject), array($this->_salesforceObject->Id => $this->_response));

                $logger->send();
            }
        }

        return $_entity;
    }

    /**
     * @param $_field
     * Replace standard field with Person Account equivalent
     */
    protected function _replacePersonField($contactField, $personAccountField = null, &$object)
    {
        if (!$personAccountField || is_numeric($personAccountField)) {
            $personAccountField = 'Person' . $contactField;
        }

        if (property_exists($object, $personAccountField)) {

            $object->{$contactField} = $object->{$personAccountField};
            unset($object->{$personAccountField});
        }
    }

    /**
     * rename PersonAccount fields for Contact mapping compatibility
     * @param $object
     */
    protected function _fixPersonAccountFields(&$object)
    {
        $_standardFields = array(
            /**
             * Contact fields
             */
            'Birthdate',
            'AssistantPhone',
            'AssistantName',
            'Department',
            'DoNotCall',
            'Email',
            'HasOptedOutOfEmail',
            'HasOptedOutOfFax',
            'LastCURequestDate',
            'LastCUUpdateDate',
            'LeadSource',
            'MobilePhone',
            'OtherPhone',
            'Title',

            /**
             *  PersonAccount field => Contact field
             */
            'BillingStreet' => 'OtherStreet',
            'BillingCity' => 'OtherCity',
            'BillingState' => 'OtherState',
            'BillingPostalCode' => 'OtherPostalCode',
            'BillingCountry' => 'OtherCountry',
            'ShippingStreet' => 'MailingStreet',
            'ShippingCity' => 'MailingCity ',
            'ShippingState' => 'MailingState ',
            'ShippingPostalCode' => 'MailingPostalCode',
            'ShippingCountry' => 'MailingCountry',
            'PersonHomePhone' => 'Phone',
        );

        foreach ($_standardFields as  $personAccountField => $contactField) {
            $this->_replacePersonField($contactField, $personAccountField, $object);
        }

        /**
         * the PersonAccount field names have "__pc" postfix, but Contact field names have the "__c" postfix
         */
        foreach ($object as $personAccountField => $value) {
            if (preg_match('/^.*__pc$/', $personAccountField)) {
                unset($object->$personAccountField);
                $personAccountField = preg_replace('/__pc$/', '__c', $personAccountField);
                $object->$personAccountField = $value;
            }
        }

    }

    protected function _prepare()
    {
        parent::_prepare();

        if (empty($this->_attributes)) {
            $resource = Mage::getResourceModel('eav/entity_attribute');
            $this->_attributes['created_in'] = $resource->getIdByCode('customer', 'created_in');
            $this->_attributes['sf_insync'] = $resource->getIdByCode('customer', 'sf_insync');
            $this->_attributes['salesforce_id'] = $resource->getIdByCode('customer', 'salesforce_id');
            $this->_attributes['salesforce_account_id'] = $resource->getIdByCode('customer', 'salesforce_account_id');
            $this->_attributes['salesforce_is_person'] = $resource->getIdByCode('customer', 'salesforce_is_person');
            $this->_attributes['password_hash'] = $resource->getIdByCode('customer', 'password_hash');
        }
        $this->_mapCollection = Mage::getModel('tnw_salesforce/mapping')->getCollection()->addObjectToFilter('Contact');

        if (!$this->_customer) {
            $this->_customer = Mage::getModel('customer/customer');
        }

        if (
            is_object($this->_salesforceObject)
            && property_exists($this->_salesforceObject, 'IsPersonAccount')
            && $this->_salesforceObject->IsPersonAccount == 1
        ) {
            $this->_salesforceObject->Email = (property_exists($this->_salesforceObject, "PersonEmail") && $this->_salesforceObject->PersonEmail) ? $this->_salesforceObject->PersonEmail : NULL;
            $this->_salesforceObject->AccountId = (property_exists($this->_salesforceObject, "Id") && $this->_salesforceObject->Id) ? $this->_salesforceObject->Id : NULL;
            $this->_isPersonAccount = true;

            $this->_fixPersonAccountFields($this->_salesforceObject);
        }

        $this->_email = (is_object($this->_salesforceObject) && property_exists($this->_salesforceObject, "Email") && $this->_salesforceObject->Email) ? strtolower($this->_salesforceObject->Email) : NULL;
        $this->_accountId = (is_object($this->_salesforceObject) && property_exists($this->_salesforceObject, "AccountId") && $this->_salesforceObject->AccountId) ? $this->_salesforceObject->AccountId : NULL;
        $this->_salesforceId = (is_object($this->_salesforceObject) && property_exists($this->_salesforceObject, "Id") && $this->_salesforceObject->Id) ? $this->_salesforceObject->Id : NULL;
        $this->_magentoId = (is_object($this->_salesforceObject) && property_exists($this->_salesforceObject, $this->_magentoIdField) && $this->_salesforceObject->{$this->_magentoIdField}) ? $this->_salesforceObject->{$this->_magentoIdField} : NULL;

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Preparation Complete ...");
    }

    protected function _updateMagento()
    {
        try {
            set_time_limit(30);

            // Creating Customer Entity
            if ($this->_isNew) {
                $_entity = Mage::getModel('customer/customer');
                if ($this->_magentoId) {
                    $_entity->setId($this->_magentoId);
                }

                $this->_response->created = true;
            } else {
                $_entity = Mage::getModel('customer/customer')->load($this->_magentoId);

                $this->_response->created = false;
            }

            if ($this->_skip) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Brand new customer or guest, see connector configuration ...");
                return $_entity;
            }

            $_additional = array(
                'billing' => array(),
                'shipping' => array(),
                'customer_group' => array()
            );

            // get attribute collection
            foreach ($this->_mapCollection as $_mapping) {
                $_value = NULL;
                if (strpos($_mapping->getLocalField(), 'Customer : ') === 0) {
                    // Product
                    $_magentoFieldName = str_replace('Customer : ', '', $_mapping->getLocalField());

                    if (property_exists($this->_salesforceObject, $_mapping->getSfField())) {
                        // get attribute object
                        $localFieldAr = explode(":", $_mapping->getLocalField());
                        $localField = trim(array_pop($localFieldAr));
                        $attOb = Mage::getModel('eav/config')->getAttribute('customer', $localField);

                        // here we set value depending of the attr type
                        if ($attOb->getFrontendInput() == 'select') {
                            // it's drop down attr type
                            $attOptionList = $attOb->getSource()->getAllOptions(true, true);
                            $_value = false;
                            foreach ($attOptionList as $key => $value) {

                                // we compare sf value with mage default value or mage locate related value (if not english lang is set)
                                $sfField = mb_strtolower($this->_salesforceObject->{$_mapping->getSfField()}, 'UTF-8');
                                $mageAttValueDefault = mb_strtolower($value['label'], 'UTF-8');

                                if (in_array($sfField, array($mageAttValueDefault))) {
                                    $_value = $value['value'];
                                }
                            }
                            // the product code not found, skipping
                            if (empty($_value)) {
                                $sfValue = $this->_salesforceObject->{$_mapping->getSfField()};
                                Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: customer code $sfValue not found in magento");
                                continue;
                            }
                        } elseif ($_mapping->getBackendType() == "datetime" || $_magentoFieldName == 'created_at' || $_magentoFieldName == 'updated_at' || $_mapping->getBackendType() == "date") {
                            $_value = gmdate(DATE_ATOM, Mage::getModel('core/date')->gmtTimestamp(strtotime($this->_salesforceObject->{$_mapping->getSfField()})));
                        } elseif ($_magentoFieldName == 'website_ids') {
                            // website ids hack
                            $_value = explode(',', $this->_salesforceObject->{$_mapping->getSfField()});
                        } else {
                            $_value = $this->_salesforceObject->{$_mapping->getSfField()};
                        }
                    } elseif ($this->_isNew && $_mapping->getDefaultValue()) {
                        $_value = $_mapping->getDefaultValue();
                    }
                    if ($_value) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer: ' . $_magentoFieldName . ' = ' . $_value);
                        $_entity->setData($_magentoFieldName, $_value);
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING Customer: ' . $_magentoFieldName . ' - no value specified in Salesforce');
                    }
                } elseif (strpos($_mapping->getLocalField(), 'Shipping : ') === 0) {
                    // Shipping Address
                    $_magentoFieldName = str_replace('Shipping : ', '', $_mapping->getLocalField());
                    if (property_exists($this->_salesforceObject, $_mapping->getSfField())) {
                        $_value = $this->_salesforceObject->{$_mapping->getSfField()};
                        if ($_value) {
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer Shipping Address: ' . $_magentoFieldName . ' = ' . $_value);
                            $_additional['shipping'][$_magentoFieldName] = $_value;
                        } else {
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING Customer Shipping Address: ' . $_magentoFieldName . ' - no value specified in Salesforce');
                        }
                    }
                } elseif (strpos($_mapping->getLocalField(), 'Billing : ') === 0) {
                    // Billing Address
                    $_magentoFieldName = str_replace('Billing : ', '', $_mapping->getLocalField());
                    if (property_exists($this->_salesforceObject, $_mapping->getSfField())) {
                        $_value = $this->_salesforceObject->{$_mapping->getSfField()};
                        if ($_value) {
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer Billing Address: ' . $_magentoFieldName . ' = ' . $_value);
                            $_additional['billing'][$_magentoFieldName] = $_value;
                        } else {
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING Customer Billing Address: ' . $_magentoFieldName . ' - no value specified in Salesforce');
                        }
                    }
                } elseif (strpos($_mapping->getLocalField(), 'Customer Group : ') === 0) {
                    // Do we need to sync this?
                    if (property_exists($this->_salesforceObject, $_mapping->getSfField())) {
                        $_value = $this->_salesforceObject->{$_mapping->getSfField()};
                        $targetGroup = Mage::getModel('customer/group');
                        $targetGroup->load($_value, 'customer_group_code');
                        if (is_object($targetGroup) && $targetGroup->getId()) {
                            $_entity->setData('group_id', $targetGroup->getId());
                        }
                    }

                }
            }

            // Update Website association
            if ($this->_websiteId) {
                $_entity->setData('website_id', $this->_websiteId);
            }

            // Set Store ID for new customer records, use Default store
            if ($_entity->getData('website_id') && $_entity->getData('store_id') === NULL) {
                $_entity->setData('store_id', Mage::app()->getWebsite($_entity->getWebsiteId())->getDefaultStore()->getId());
            }

            // Increase the timeout
            set_time_limit(120);

            $_flag = false;
            if (!Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('core/session')->setFromSalesForce(true);
                $_flag = true;
            }

            $_currentTime = $this->_getTime();
            if (!$_entity->getData('updated_at') || $_entity->getData('updated_at') == '') {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer: updated_at = ' . $_currentTime);
                $_entity->setData('updated_at', $_currentTime);
            }
            if (!$_entity->getData('created_at') || $_entity->getData('created_at') == '') {
                if (property_exists($this->_salesforceObject, 'CreatedDate')) {
                    $_currentTime = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(strtotime($this->_salesforceObject->CreatedDate)));
                }
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer: created_at = ' . $_currentTime);
                $_entity->setData('created_at', $_currentTime);
            }

            // Set / Update sync params
            if (property_exists($this->_salesforceObject, 'Id')) {
                // Fix for PersonAccount
                if (
                    property_exists($this->_salesforceObject, 'IsPersonAccount')
                    && $this->_salesforceObject->IsPersonAccount
                    && property_exists($this->_salesforceObject, 'PersonContactId')
                ) {
                    $this->_salesforceObject->Id = $this->_salesforceObject->PersonContactId;
                }

                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer: salesforce_id = ' . $this->_salesforceObject->Id);
                $_entity->setData('salesforce_id', $this->_salesforceObject->Id);
            }
            if (property_exists($this->_salesforceObject, 'AccountId')) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer: salesforce_account_id = ' . $this->_salesforceObject->AccountId);
                $_entity->setData('salesforce_account_id', $this->_salesforceObject->AccountId);
            }
            if (property_exists($this->_salesforceObject, 'IsPersonAccount') && $this->_salesforceObject->IsPersonAccount) {
                $_entity->setData('salesforce_is_person', 1);
            }

            $_entity->setData('sf_insync', 1);

            // Save Customer
            $_entity->save();

            if (!$this->_magentoId) {
                $this->_magentoId = $_entity->getId();
            }

            // Update Subscription
            if (
                Mage::helper('tnw_salesforce')->getCustomerNewsletterSync()
                && (
                    property_exists($this->_salesforceObject, 'HasOptedOutOfEmail')
                    || property_exists($this->_salesforceObject, 'PersonHasOptedOutOfEmail')
                )
            ) {
                $_field = (property_exists($this->_salesforceObject, 'HasOptedOutOfEmail')) ? 'HasOptedOutOfEmail' : 'PersonHasOptedOutOfEmail';
                $subscriber = Mage::getModel('newsletter/subscriber')->loadByCustomer($_entity);
                if (!$this->_salesforceObject->{$_field} && !$subscriber->isSubscribed()) {
                    if ($_entity->getData('email')) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Subscribing: ' . $_entity->getData('email'));
                        $subscriber->setStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
                        $storeId = $_entity->getStoreId();
                        if ($_entity->getStoreId() == 0) {
                            $storeId = Mage::app()->getWebsite($_entity->getWebsiteId())->getDefaultStore()->getId();
                        }
                        $subscriber
                            ->setStoreId($storeId)
                            ->setEmail($_entity->getEmail())
                            ->setCustomerId($_entity->getId())
                        ;
                        $subscriber->save();
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Subscribed!');
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING Customer Subscription: Customer (' . $_entity->getData('firstname') . ' ' . $_entity->getData('lastname') . ') does not have an email in Salesforce!');
                    }
                } elseif ($this->_salesforceObject->{$_field} && $subscriber->isSubscribed()) {
                    $subscriber->unsubscribe();
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($_entity->getData('email') . ' un-subscribed!');
                }
            }

            // Do Additional Stuff
            foreach($_additional as $_key => $_data) {
                if (!empty($_data) && ($_key == 'shipping' || $_key == 'billing')) {
                    $this->_countryCode = NULL;
                    $this->_regionCode = NULL;

                    $_addressId = $this->_addressLookup($_data, $_entity);

                    $_address = Mage::getModel('customer/address');
                    if ($_addressId) {
                        $_address->load($_addressId);
                    }
                    if (array_key_exists('street', $_additional[$_key])) {
                        $_fromSalesforce = $_data['street'];
                        $_data['street'] = array(
                            '0' => $_fromSalesforce,
                            '1' => ''
                        );
                    }

                    // Set Telephone
                    if (
                        !array_key_exists('telephone', $_additional[$_key])
                        && property_exists($this->_salesforceObject, 'Phone')
                    ) {
                        $_data['telephone'] = $this->_salesforceObject->Phone;
                    }

                    // Set First Name
                    if (
                        !array_key_exists('firstname', $_additional[$_key])
                        && property_exists($this->_salesforceObject, 'FirstName')
                    ) {
                        $_data['firstname'] = $this->_salesforceObject->FirstName;
                    }

                    // Set Last Name
                    if (
                        !array_key_exists('lastname', $_additional[$_key])
                        && property_exists($this->_salesforceObject, 'LastName')
                    ) {
                        $_data['lastname'] = $this->_salesforceObject->LastName;
                    }

                    // Make sure core data is correct
                    $_data['parent_id'] = $this->_magentoId;
                    $_data['region_id'] = $this->_regionCode;
                    $_data['country_id'] = $this->_countryCode;

                    // Set Data
                    $_address->setData($_data);

                    if($_addressId) {
                        $_address->setId($_addressId);
                    }

                    // Save in address book
                    $_address->setSaveInAddressBook('1');

                    // Set IsDefault
                    if ($_key == 'billing') {
                        $_address->setIsDefaultBilling('1');
                    }
                    if ($_key == 'shipping') {
                        $_address->setIsDefaultShipping('1');
                    }

                    try {
                        $_address->save();
                    } catch (Exception $e) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting customer address into Magento: " . $e->getMessage());
                    }
                }
            }

            if ($_flag) {
                Mage::getSingleton('core/session')->setFromSalesForce(false);
            }
            // Reset timeout
            set_time_limit(30);

            return $_entity;
        } catch (Exception $e) {
            $this->_addError('Error upserting customer into Magento: ' . $e->getMessage(), 'MAGENTO_CUSTOMER_UPSERT_FAILED');
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting customer into Magento: " . $e->getMessage());
            unset($e);
            return false;
        }
    }

    /**
     * Accepts a single customer object and upserts a contact into the DB
     *
     * @param null $object
     * @return bool|false|Mage_Core_Model_Abstract
     */
    public function syncFromSalesforce()
    {
        // Pre config, settings, etc
        parent::_prepare();

        if (!$this->_salesforceId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting customer into Magento: Contact ID is missing");
            $this->_addError('Could not upsert Product into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }
        if (!$this->_email && !$this->_magentoId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting customer into Magento: Email and Magento ID are missing");
            $this->_addError('Error upserting customer into Magento: Email and Magento ID are missing', 'EMAIL_AND_MAGENTO_ID_MISSING');
            return false;
        }

        try {

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Trying to find customer in Magento');
            $this->_skip = false;   // Reset the flag
            $this->_findMagentoCustomer();

        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: ' . $e->getMessage());
            $this->_addError('Customer location failed: ' . $e->getMessage(), 'CUSTOMER_FINDER_FAILED');
            return false;
        }

        if ($this->_groupId === NULL) {
            // New Customer
            // Check if Group mapping exist and getId based on Name

            // TODO: add Magneto config to specify Default customer group to use here
            $this->_groupId = 0;
        }
        if ($this->_groupId === NULL || (!Mage::helper('tnw_salesforce')->getSyncAllGroups() && !Mage::helper('tnw_salesforce')->syncCustomer($this->_groupId))) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Sync for group #" . $this->_groupId . " is disabled! Customer (" . $this->_email . ")");
            $this->_addError("Sync for group #" . $this->_groupId . " is disabled! Customer (" . $this->_email . ")", 'CUSTOMER_SKIPPED');
            return false;
        }

        return $this->_updateMagento();
    }

    protected function _findMagentoCustomer() {
        if (property_exists($this->_salesforceObject, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject())) {
            $_websiteSfId = $this->_salesforceObject->{Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()};
            $_websiteId = array_search($_websiteSfId, $this->_websiteSfIds);
            if ($_websiteId) {
                $this->_websiteId = $_websiteId;
            }
        }

        // Magneto ID provided
        if ($this->_magentoId) {
            //Test if user exists
            $sql = "SELECT entity_id, group_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` WHERE entity_id = '" . $this->_magentoId . "'";
            $row = $this->_write->query($sql)->fetch();
            if (!$row) {
                // Magento ID exists in Salesforce, user must have been deleted. Will re-create with the same ID
                $this->_isNew = true;
            } else {
                $this->_groupId = $row['group_id'];
            }
        }
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('------------------');
        if ($this->_magentoId && !$this->_isNew) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Customer Loaded by using Magento ID: " . $this->_magentoId);
        } else {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Possibly a New Customer');
            // No Magento ID
            if ($this->_salesforceId && !$this->_magentoId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Find by SF Id');
                // Try to find the user by SF Id
                $sql = "SELECT entity_id FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity_varchar') . "` WHERE value = '" . $this->_salesforceId . "' AND attribute_id = '" . $this->_attributes['salesforce_id'] . "' AND entity_type_id = '1'";
                $row = $this->_write->query($sql)->fetch();
                $this->_magentoId = ($row) ? $row['entity_id'] : NULL;
            }

            if ($this->_magentoId) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Customer #" . $this->_magentoId . " Loaded by using Salesforce ID: " . $this->_salesforceId);
                $sql = "SELECT entity_id, group_id  FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` WHERE entity_id = '" . $this->_magentoId . "'";
                $row = $this->_write->query($sql)->fetch();
                $this->_groupId = ($row) ? $row['group_id'] : NULL;
            } else {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Find by email');
                //Last reserve, try to find by email
                $sql = "SELECT entity_id, group_id FROM `" . Mage::helper('tnw_salesforce')->getTable('customer_entity') . "` WHERE email = '" . $this->_email . "'";
                if ($this->_websiteId && Mage::helper('tnw_salesforce')->getCustomerScope() == "1") {
                    $sql .= " AND website_id = '" . $this->_websiteId . "'";
                }

                $row = $this->_write->query($sql)->fetch();
                $this->_magentoId = ($row) ? $row['entity_id'] : NULL;
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('MID by email: ' . $this->_magentoId);
                if ($this->_magentoId) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Customer #" . $this->_magentoId . " Loaded by using Email: " . $this->_email);
                    $this->_groupId = $row['group_id'];
                } else {
                    //Brand new user
                    $this->_isNew = true;
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("New Customer. Creating!");
                }
            }
        }

        if ($this->_isNew) {
            // Try to find Group from mappings
            $this->_groupId = $this->_getCustomerGroupFromSalesforce();
        }

        if (!Mage::helper('tnw_salesforce/config_customer')->allowSalesforceToCreate()) {
            $this->_skip = true;
        }
    }

    protected function _getCustomerGroupFromSalesforce() {
        foreach ($this->_mapCollection as $_mapping) {
            if (strpos($_mapping->getLocalField(), 'Customer : group_id') === 0) {
                $_groupName = $this->_salesforceObject->{$_mapping->getSfField()};
                foreach (Mage::getModel('customer/group_api')->items() as $_item) {
                    if ($_groupName == $_item['customer_group_code']) {
                        return $_item['customer_group_id'];
                    }
                }
            }
        }
        return NULL;
    }

    protected function _addressLookup($_data = array(), $_entity = NULL) {
        $this->_countryCode = $this->_getCountryId($_data['country_id']);
        if ($this->_countryCode) {
            $this->_regionCode = $this->_getRegionId($_data['region'], $this->_countryCode);
        }

        foreach($_entity->getAddresses() as $_address) {
            $_street = trim(join(' ', $_address->getStreet()));
            if (
                $_address->getCountryId() == $this->_countryCode
                && $_address->getRegionId() == $this->_regionCode
                && $_address->getCity() == $_data['city']
                && $_address->getPostcode() == $_data['postcode']
                && trim($_street) == $_data['street']
            ) {
                return $_address->getId();
            }
        }
        return false;
    }

    public function _getCountryId($_name  = NULL) {
        foreach(Mage::getModel('directory/country_api')->items() as $_country) {
            if (in_array($_name, $_country)) {
                return $_country['country_id'];
            }
        }
        return NULL;
    }

    public function _getRegionId($_name  = NULL, $_countryCode = NULL) {
        foreach(Mage::getModel('directory/region_api')->items($_countryCode) as $_region) {
            if (in_array($_name, $_region)) {
                return $_region['region_id'];
            }
        }
        return NULL;
    }

    protected function _assignCustomerToOrder($_customerEmail, $_customerId)
    {
        if (!$_customerId || !$_customerEmail) {
            return;
        }
        $orders = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToSelect('entity_id')
            ->addFieldToFilter('customer_email', $_customerEmail)
            ->addFieldToFilter('customer_id', array('null' => true));
        if ($orders && !empty($orders)) {
            $sql = "";
            $_ordersUpdated = array();
            foreach ($orders as $_order) {
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order') . "` SET customer_id = " . $_customerId . " WHERE entity_id = " . $_order['entity_id'] . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_grid') . "` SET customer_id = " . $_customerId . " WHERE entity_id = " . $_order['entity_id'] . ";";
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('sales_flat_order_address') . "` SET customer_id = " . $_customerId . " WHERE parent_id = " . $_order['entity_id'] . ";";
                $_ordersUpdated[] = $_order['entity_id'];
            }
            if (!empty($sql)) {
                $this->_write->query($sql);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Orders: (" . join(', ', $_ordersUpdated) . ") were associated with customer (" . $_customerEmail . ").");
            }
        }
    }
}