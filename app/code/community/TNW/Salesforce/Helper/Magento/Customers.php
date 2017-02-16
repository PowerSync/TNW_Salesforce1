<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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
     * @var TNW_Salesforce_Model_Mysql4_Mapping_Collection
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
     * @var null|stdClass
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

        if ($type = "Account"
            && property_exists($this->_salesforceObject, 'IsPersonAccount')
            && $this->_salesforceObject->IsPersonAccount == true)
        {
            $_type = 'Contact';
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("** " . $_type . " #" . $this->_salesforceObject->Id . " **");

        $_entity = $this->syncFromSalesforce($this->_salesforceObject);

        if (!$this->_skip) {
            // Handle success and fail
            if (is_object($_entity)) {
                // Update history orders and assigne to customer we just created
                $this->_assignCustomerToOrder($_entity->getData('email'), $_entity->getId());

                $magentoId   = $this->_getEntityNumber($_entity);
                if ($this->_getSfMagentoId($this->_salesforceObject) != $magentoId) {
                    $this->_salesforceAssociation[$_type][] = array(
                        'salesforce_id' => $_entity->getData('salesforce_id'),
                        'magento_id'    => $magentoId
                    );
                }

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
            'BillingStateCode' => 'OtherStateCode',
            'BillingPostalCode' => 'OtherPostalCode',
            'BillingCountry' => 'OtherCountry',
            'BillingCountryCode' => 'OtherCountryCode',
            'ShippingStreet' => 'MailingStreet',
            'ShippingCity' => 'MailingCity',
            'ShippingState' => 'MailingState',
            'ShippingStateCode' => 'MailingStateCode',
            'ShippingPostalCode' => 'MailingPostalCode',
            'ShippingCountry' => 'MailingCountry',
            'ShippingCountryCode' => 'MailingCountryCode',
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

        $this->_mapCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
            ->addObjectToFilter('Contact')
            ->addFieldToFilter('sf_magento_enable', 1)
            ->firstSystem();

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
            /** @var Mage_Customer_Model_Customer $_entity */
            $_entity = Mage::getModel('customer/customer');
            if ($this->_isNew) {
                $this->_response->created = true;
            } else {
                $_entity->load($this->_magentoId);
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

            $this->_mapCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
                ->addObjectToFilter('Contact')
                ->addFilterTypeSM(!$_entity->isObjectNew())
                ->firstSystem();

            /** @var TNW_Salesforce_Model_Mapping $_mapping */
            foreach ($this->_mapCollection as $_mapping) {
                $value = property_exists($this->_salesforceObject, $_mapping->getSfField())
                    ? $this->_salesforceObject->{$_mapping->getSfField()} : null;

                if (strpos($_mapping->getLocalField(), 'Customer : ') === 0) {
                    Mage::getSingleton('tnw_salesforce/mapping_type_customer')
                        ->setMapping($_mapping)
                        ->setValue($_entity, $value);

                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace('Customer: ' . $_mapping->getLocalFieldAttributeCode() . ' = ' . var_export($_entity->getData($_mapping->getLocalFieldAttributeCode()), true));
                } elseif (strpos($_mapping->getLocalField(), 'Shipping : ') === 0) {
                    // Shipping Address
                    if (empty($value)) {
                        $value = $_mapping->getDefaultValue();
                    }

                    $_magentoFieldName = str_replace('Shipping : ', '', $_mapping->getLocalField());
                    if ($value) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer Shipping Address: ' . $_magentoFieldName . ' = ' . $value);
                        $_additional['shipping'][$_magentoFieldName] = $value;
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING Customer Shipping Address: ' . $_magentoFieldName . ' - no value specified in Salesforce');
                    }
                } elseif (strpos($_mapping->getLocalField(), 'Billing : ') === 0) {
                    // Billing Address
                    if (empty($value)) {
                        $value = $_mapping->getDefaultValue();
                    }

                    $_magentoFieldName = str_replace('Billing : ', '', $_mapping->getLocalField());
                    if ($value) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer Billing Address: ' . $_magentoFieldName . ' = ' . $value);
                        $_additional['billing'][$_magentoFieldName] = $value;
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING Customer Billing Address: ' . $_magentoFieldName . ' - no value specified in Salesforce');
                    }
                } elseif (strpos($_mapping->getLocalField(), 'Customer Group : ') === 0) {
                    // Do we need to sync this?
                    if (empty($value)) {
                        $value = $_mapping->getDefaultValue();
                    }

                    if ($value) {
                        $targetGroup = Mage::getModel('customer/group');
                        $targetGroup->load($value, 'customer_group_code');
                        if (is_object($targetGroup) && $targetGroup->getId()) {
                            $_entity->setData('group_id', $targetGroup->getId());
                        }
                    }

                }
            }

            $_mapCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
                ->addObjectToFilter('Account')
                ->addFilterTypeSM(!$_entity->isObjectNew())
                ->firstSystem();

            /** @var TNW_Salesforce_Model_Mapping $_mapping */
            foreach ($_mapCollection as $_mapping) {
                $objectAccount = !$this->_isPersonAccount
                    ? $this->_salesforceObject->Account : $this->_salesforceObject;

                $value = property_exists($objectAccount, $_mapping->getSfField())
                    ? $objectAccount->{$_mapping->getSfField()} : null;

                if (strcasecmp($_mapping->getLocalFieldType(), 'Customer') === 0) {
                    Mage::getSingleton('tnw_salesforce/mapping_type_customer')
                        ->setMapping($_mapping)
                        ->setValue($_entity, $value);

                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace('Customer: ' . $_mapping->getLocalFieldAttributeCode() . ' = ' . var_export($_entity->getData($_mapping->getLocalFieldAttributeCode()), true));
                } elseif (strcasecmp($_mapping->getLocalFieldType(), 'Shipping') === 0) {
                    // Shipping Address
                    if (empty($value)) {
                        $value = $_mapping->getDefaultValue();
                    }

                    $_magentoFieldName = str_replace('Shipping : ', '', $_mapping->getLocalField());
                    if ($value) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer Shipping Address: ' . $_magentoFieldName . ' = ' . $value);
                        $_additional['shipping'][$_magentoFieldName] = $value;
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING Customer Shipping Address: ' . $_magentoFieldName . ' - no value specified in Salesforce');
                    }
                } elseif (strcasecmp($_mapping->getLocalFieldType(), 'Billing') === 0) {
                    // Billing Address
                    if (empty($value)) {
                        $value = $_mapping->getDefaultValue();
                    }

                    $_magentoFieldName = str_replace('Billing : ', '', $_mapping->getLocalField());
                    if ($value) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Customer Billing Address: ' . $_magentoFieldName . ' = ' . $value);
                        $_additional['billing'][$_magentoFieldName] = $value;
                    } else {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPPING Customer Billing Address: ' . $_magentoFieldName . ' - no value specified in Salesforce');
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

            //Send Welcome and Password
            if ($this->_isNew) {
                $_entity->changePassword($_entity->generatePassword());

                switch (Mage::helper('tnw_salesforce/config_customer')->sendTypeEmailCustomer()) {
                    case TNW_Salesforce_Model_System_Config_Source_Customer_EmailNew::SEND_WELCOME;
                        $_entity->sendNewAccountEmail('registered', '', $_entity->getStoreId());

                    case TNW_Salesforce_Model_System_Config_Source_Customer_EmailNew::SEND_PASSWORD;
                        $_entity->sendPasswordReminderEmail();
                }
            }

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

            $_addressesIsDifferent = false;
            foreach (array('street', 'city', 'region', 'region_id', 'postcode', 'country_id') as $_field) {
                if (strcasecmp(@$_additional['shipping'][$_field], @$_additional['billing'][$_field]) != 0) {
                    $_addressesIsDifferent = true;
                    break;
                }
            }

            $_addressesLookup = array_filter(array(
                'shipping' => $this->_addressLookup($_additional['shipping'], $_entity),
                'billing'  => $this->_addressLookup($_additional['billing'], $_entity)
            ));

            if (!$_addressesIsDifferent) {
                $_addressesDefault = array_intersect($_addressesLookup, array(
                    $_entity->getData('default_shipping'),
                    $_entity->getData('default_billing')
                ));

                $_addressShippingId = $_addressBillingId = (!empty($_addressesDefault))
                    ? reset($_addressesDefault)
                    : reset($_addressesLookup);
            }
            else {
                $_addressShippingId = isset($_addressesLookup['shipping'])
                    ? $_addressesLookup['shipping']
                    : $_entity->getData('default_shipping');

                $_addressBillingId  = isset($_addressesLookup['billing'])
                    ? $_addressesLookup['billing']
                    : $_entity->getData('default_billing');

                if ($_addressShippingId == $_addressBillingId) {
                    $_addressBillingId = null;
                }
            }

            // Do Additional Stuff
            foreach($_additional as $_key => $_data) {
                if (empty($_data)) {
                    continue;
                }

                switch ($_key) {
                    case 'shipping':
                    case 'billing':

                        $_countryCode = $this->_getCountryId($_data['country_id']);
                        $_regionCode  = null;
                        if ($_countryCode) {
                            foreach (array('region_id', 'region') as $_regionField) {
                                if (!isset($_data[$_regionField])) {
                                    continue;
                                }

                                $_regionCode = $this->_getRegionId($_data[$_regionField], $_countryCode);
                                if (!empty($_regionCode)) {
                                    break;
                                }
                            }
                        }

                        /** @var Mage_Customer_Model_Address $_address */
                        $_address = Mage::getModel('customer/address');

                        $_addressId = ($_key == 'shipping')
                            ? $_addressShippingId
                            : $_addressBillingId;

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
                        $_data['region_id'] = $_regionCode;
                        $_data['country_id'] = $_countryCode;

                        // Set Data
                        $_address->addData($_data);

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

                            if (!$_addressesIsDifferent) {
                                $_addressShippingId = $_addressBillingId = $_address->getId();
                            }

                            $_entity->getAddressesCollection()->resetData();

                            if ($_address->getIsDefaultBilling()) {
                                $_entity->setDefaultBilling($_address->getId());
                            }
                            if ($_address->getIsDefaultShipping()) {
                                $_entity->setDefaultShipping($_address->getId());
                            }
                        } catch (Exception $e) {
                            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting customer address into Magento: " . $e->getMessage());
                        }

                        break;
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
    public function syncFromSalesforce($object = null)
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

    protected function _findMagentoCustomer()
    {
        $_websiteSfField = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix()
            . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject();

        $_websiteSfId = property_exists($this->_salesforceObject, $_websiteSfField)
            ? Mage::helper('tnw_salesforce')->prepareId($this->_salesforceObject->{$_websiteSfField}) : null;

        $_websiteId = array_search($_websiteSfId, $this->_websiteSfIds);
        $this->_websiteId = $_websiteId === false
            ? Mage::app()->getWebsite(true)->getId() : $_websiteId;

        $entityTable = Mage::helper('tnw_salesforce')->getTable('customer_entity');
        $entityVarcharTable = Mage::helper('tnw_salesforce')->getTable('customer_entity_varchar');

        $mMagentoId = $mGroupId = null;

        // Magneto ID provided
        if ($this->_magentoId) {
            //Test if user exists
            $sql = "SELECT entity_id, group_id  FROM `$entityTable` WHERE entity_id = '{$this->_magentoId}'";
            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $mMagentoId = $row['entity_id'];
                $mGroupId   = $row['group_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Customer Loaded by using Magento ID: " . $this->_magentoId);
            }
        }

        if (empty($mMagentoId) && $this->_salesforceId) {
            // Try to find the user by SF Id
            $sql = "SELECT entity.entity_id, entity.group_id FROM `$entityVarcharTable` as attr "
                ."INNER JOIN `$entityTable` as entity ON attr.entity_id = entity.entity_id "
                ."WHERE attr.value = '{$this->_salesforceId}' "
                    ."AND attr.attribute_id = '{$this->_attributes['salesforce_id']}' "
                    ."AND attr.entity_type_id = '1'";

            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $mMagentoId = $row['entity_id'];
                $mGroupId   = $row['group_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Customer #$mMagentoId Loaded by using Salesforce ID: {$this->_salesforceId}");
            }
        }

        if (empty($mMagentoId) && $this->_email) {
            $sql = "SELECT entity_id, group_id FROM `$entityTable` WHERE email = '{$this->_email}'";
            if ($this->_websiteId && Mage::helper('tnw_salesforce')->getCustomerScope() == "1") {
                $sql .= " AND website_id = '" . $this->_websiteId . "'";
            }
            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $mMagentoId = $row['entity_id'];
                $mGroupId   = $row['group_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Customer #$mMagentoId Loaded by using Email: {$this->_email}");
            }
        }

        $this->_isNew = is_null($mMagentoId);
        if ($this->_isNew) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("New Customer. Creating!");

            // Try to find Group from mappings
            $this->_groupId   = $this->_getCustomerGroupFromSalesforce();
            $this->_magentoId = null;

            if (!Mage::helper('tnw_salesforce/config_customer')->allowSalesforceToCreate()) {
                $this->_skip = true;
            }
        }
        else {
            $this->_magentoId = $mMagentoId;
            $this->_groupId   = $mGroupId;
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

    /**
     * @param array $_data
     * @param Mage_Customer_Model_Customer $_entity
     * @return bool
     */
    protected function _addressLookup($_data = array(), $_entity = NULL)
    {
        $this->_countryCode = $this->_getCountryId($_data['country_id']);
        $this->_regionCode  = null;
        if ($this->_countryCode) {
            foreach (array('region_id', 'region') as $_regionField) {
                if (!isset($_data[$_regionField])) {
                    continue;
                }

                $this->_regionCode = $this->_getRegionId($_data[$_regionField], $this->_countryCode);
                if (!empty($this->_regionCode)) {
                    break;
                }
            }
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