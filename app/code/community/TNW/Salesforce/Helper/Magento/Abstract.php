<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */
abstract class TNW_Salesforce_Helper_Magento_Abstract
{
    /**
     * @var array
     */
    protected $_entitiesToSave = array();

    /**
     * @var null
     */
    protected $_salesforceAssociation = array();

    /**
     * @var null
     */
    protected $_write = null;

    /**
     * @var null
     */
    protected $_response = NULL;

    /**
     * @var null
     */
    protected $_prefix = NULL;

    /**
     * @var null
     */
    protected $_magentoIdField = NULL;

    /**
     * @var array
     */
    protected $_websiteSfIds = array();

    /* Core functions */
    protected $_time = NULL;

    /**
     * @return null
     */
    public function getSalesforceAssociationAndClean()
    {
        $_association = $this->_salesforceAssociation;
        $this->_salesforceAssociation = array();

        return $_association;
    }

    /**
     * @param null $_object
     * @return bool|false|Mage_Core_Model_Abstract
     */
    public function process($_object = null)
    {
        if (!$_object || !Mage::helper('tnw_salesforce')->isWorking()) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("No Salesforce object passed on connector is not working");
            return false;
        }

        $this->_response = new stdClass();
        $_type = $_object->attributes->type;
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("** " . $_type . " #" . $_object->Id . " **");
        $_entity = $this->syncFromSalesforce($_object);
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("** finished upserting " . $_type . " #" . $_object->Id . " **");

        // Handle success and fail
        if (is_object($_entity)) {
            $this->_salesforceAssociation[$_type][] = array(
                'salesforce_id' => $_entity->getData('salesforce_id'),
                'magento_id'    => $this->_getEntityNumber($_entity)
            );

            $this->_response->success = true;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Salesforce " . $_type . " #" . $_object->Id . " upserted!");
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Magento Id: " . $_entity->getId());
        } else {
            $this->_response->success = false;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not upsert " . $_type . " into Magento, see Magento log for details");
            $_entity = false;
        }

        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            /** @var TNW_Salesforce_Helper_Report $logger */
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();
            $logger->add('Magento', $_type, array($_object->Id => $_object), array($_object->Id => $this->_response));
            $logger->send();
        }

        return $_entity;
    }

    /**
     * @param $_entity
     * @return mixed
     */
    protected function _getEntityNumber($_entity)
    {
        return $_entity->getId();
    }

    /**
     * @param null $object
     * @return mixed
     */
    abstract public function syncFromSalesforce($object = null);

    public static function sendMagentoIdToSalesforce($_association)
    {
        /** @var TNW_Salesforce_Model_Connection $_client */
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->initConnection()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR on sync entity, sf api connection failed");

            return;
        }

        foreach ($_association as $type => $item) {
            $sendData = array();

            $itemsToUpsert = array_chunk($item, TNW_Salesforce_Helper_Data::BASE_UPDATE_LIMIT, true);
            foreach ($itemsToUpsert as $_itemsToPush) {
                foreach ($_itemsToPush as $_item) {
                    if (empty($_item['salesforce_id'])) {
                        continue;
                    }

                    $sendData[] = static::_prepareEntityUpdate($_item);
                }

                try {
                    $_client->getClient()->upsert('Id', $sendData, $type);
                } catch (Exception $e) {}
            }
        }
    }

    /**
     * @param $_data
     * @return stdClass
     */
    protected static function _prepareEntityUpdate($_data)
    {
        $_obj = new stdClass();
        $_obj->Id = $_data['salesforce_id'];
        $_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . 'Magento_ID__c'} = $_data['magento_id'];

        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $_obj->{TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_ENTERPRISE . 'disableMagentoSync__c'} = true;
        }

        return $_obj;
    }

    /**
     * @param $_message
     * @param $_code
     */
    protected function _addError($_message, $_code)
    {
        if (!property_exists($this->_response, 'errors')) {
            $this->_response->errors = array();
        }
        $_error = new stdClass();
        $_error->message = $_message;
        $_error->statusCode = $_code;

        $this->_response->errors[] = $_error;
    }

    public function __construct()
    {
        $this->_setTime();
    }

    protected function _prepare()
    {
        if (!$this->_write) {
            $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        }

        // Get Website Salesforce Id's
        $website = Mage::getModel('core/website')->load(0);
        $this->_websiteSfIds[0] = $this->getWebsiteSfId($website);
        foreach (Mage::app()->getWebsites() as $website) {
            $this->_websiteSfIds[$website->getData('website_id')] = $this->getWebsiteSfId($website);
        }

        if (!$this->_magentoIdField) {
            $this->_magentoIdField = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
        }
    }

    public function getWebsiteSfId($website)
    {
        $sfId = $website->getData('salesforce_id');
        if (empty($sfId)){
            $manualSync = Mage::helper('tnw_salesforce/salesforce_website');
            if ($manualSync->reset() && $manualSync->massAdd(array($website->getData('website_id')))) {
                $manualSync->process();
                $newWebsite = Mage::getModel('core/website')->load($website->getData('website_id'));
                $sfId = $newWebsite->getData('salesforce_id');
            }
        }
        $sfId = Mage::helper('tnw_salesforce')->prepareId($sfId);
        return $sfId;
    }

    protected function _getTime()
    {
        if (!$this->_time) {
            $this->_setTime();
        }

        return $this->_time;
    }

    protected function _setTime()
    {
        $this->_time = gmdate(DATE_ATOM, Mage::getModel('core/date')->timestamp(time()));
        return $this;
    }

    /**
     * @param string $key
     * @param Mage_Core_Model_Abstract $entity
     */
    protected function addEntityToSave($key, $entity)
    {
        $this->_entitiesToSave[$key] = $entity;
    }

    /**
     * @return $this
     * @throws Exception
     */
    protected function saveEntities()
    {
        if (!empty($this->_entitiesToSave)) {
            $transaction = Mage::getSingleton('core/resource_transaction');
            foreach ($this->_entitiesToSave as $key => $entityToSave) {
                $transaction->addObject($entityToSave);
            }
            $transaction->save();
        }

        return $this;
    }

    protected function findCountryId($name)
    {
        static $country = null;
        if (is_null($country)) {
            $country = Mage::getModel('directory/country_api')->items();
        }

        foreach($country as $_country) {
            if (!in_array($name, $_country)) {
                continue;
            }

            return $_country['country_id'];
        }

        return null;
    }

    protected function findRegionId($countryCode, $region)
    {
        static $regions = array();
        if (empty($regions[$countryCode])) {
            $regions[$countryCode] = Mage::getModel('directory/region_api')
                ->items($countryCode);
        }

        foreach($regions[$countryCode] as $_region) {
            if (!in_array($region, $_region)) {
                continue;
            }

            return $_region['region_id'];
        }

        return null;
    }
}