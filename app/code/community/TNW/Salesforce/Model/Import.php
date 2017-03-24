<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * @method $this setStatus($value)
 * @method $this setMessage($value)
 */
class TNW_Salesforce_Model_Import extends Mage_Core_Model_Abstract
{
    const STATUS_NEW        = 'new';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS    = 'success';
    const STATUS_ERROR      = 'error';

    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/import');
    }

    /**
     * Get Object Type
     *
     * @return string
     */
    public function getObjectType()
    {
        $object = $this->getObject();

        return isset($object->attributes) && isset($object->attributes->type)
            ? (string)$object->attributes->type : '';
    }

    /**
     * @return mixed
     */
    public function getObject()
    {
        return json_decode($this->getData('json'));
    }

    /**
     * @param $object stdClass
     * @return $this
     */
    public function setObject($object)
    {
        return $this->setData('json', json_encode($object));
    }

    /**
     * @param stdClass $object
     * @return $this
     */
    public function importObject(stdClass $object)
    {
        if (!property_exists($object, 'Id')) {
            Mage::throwException('Invalid object');
        }

        $objectId = $object->Id;
        $this->getResource()->load($this, $objectId, 'object_id');

        $data = array(
            'object_id'     => $objectId,
            'object_type'   => !empty($object->attributes->type) ? $object->attributes->type : '',
        );

        if (in_array($this->getData('status'), array(self::STATUS_PROCESSING, self::STATUS_SUCCESS, self::STATUS_ERROR))) {
            $this->setData($data);
        }
        else {
            $this->addData($data);
        }

        return $this->setObject($object);
    }

    /**
     * Get Object property
     *
     * @param string $key
     * @return mixed
     */
    public function getObjectProperty($key)
    {
        return isset($this->getObject()->$key) ? $this->getObject()->$key : null;
    }

    /**
     * Get import processor
     * @return TNW_Salesforce_Helper_Magento_Abstract
     * @throws Exception
     */
    protected function getProcessor()
    {
        switch ($this->getObjectType()) {
            case 'Account':
            case 'Contact':
                if (($this->getObjectProperty('IsPersonAccount') && $this->getObjectProperty('PersonEmail'))
                    || $this->getObjectProperty('Email')
                ) {
                    return Mage::helper('tnw_salesforce/magento_customers');
                }
                break;
            case Mage::helper('tnw_salesforce/config')->getMagentoWebsiteField():
                return Mage::helper('tnw_salesforce/magento_websites');
            case 'Product2':
                return Mage::helper('tnw_salesforce/magento_products');
            case 'Order':
                if ($this->getObjectProperty('IsReductionOrder') && $this->getObjectProperty('OriginalOrderId')) {
                    return Mage::helper('tnw_salesforce/magento_creditmemo');
                }

                return Mage::helper('tnw_salesforce/magento_order');
            case TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT:
                return Mage::helper('tnw_salesforce/magento_invoice');
            case TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_OBJECT:
                return Mage::helper('tnw_salesforce/magento_shipment');
            case 'Opportunity':
                $magentoId = Mage::helper('tnw_salesforce/config')->getMagentoIdField();
                if (false !== strpos($this->getObjectProperty($magentoId), TNW_Salesforce_Helper_Salesforce_Wishlist::SALESFORCE_ENTITY_PREFIX)) {
                    return Mage::helper('tnw_salesforce/magento_wishlist');
                }

                return Mage::helper('tnw_salesforce/magento_opportunity');
        }

        throw new Exception('Unknown record type');
    }

    /**
     * Process import
     */
    public function process()
    {
        $_association = array();
        $importProcessor = $this->getProcessor();
        if ($importProcessor instanceof TNW_Salesforce_Helper_Magento_Abstract) {
            Mage::getSingleton('core/session')->setFromSalesForce(true);

            $importProcessor->process($this->getObject());
            $_association = $importProcessor->getSalesforceAssociationAndClean();

            Mage::getSingleton('core/session')->setFromSalesForce(false);
        }

        return $_association;
    }

    /**
     * @param $_association
     */
    public function sendMagentoIdToSalesforce($_association)
    {
        $importProcessor = $this->getProcessor();
        if ($importProcessor instanceof TNW_Salesforce_Helper_Magento_Abstract) {
            $importProcessor::sendMagentoIdToSalesforce($_association);
        }
    }
}
