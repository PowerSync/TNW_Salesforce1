<?php

class TNW_Salesforce_Model_Config_Objects
{
    const LEAD_OBJECT               =   'Lead';
    const CONTACT_OBJECT            =   'Contact';
    const ACCOUNT_OBJECT            =   'Account';
    const ABANDONED_OBJECT          =   'Abandoned';
    const ABANDONED_ITEM_OBJECT     =   'AbandonedItem';
    const OPPORTUNITY_OBJECT        =   'Opportunity';
    const OPPORTUNITY_ITEM_OBJECT   =   'OpportunityLineItem';
    const ORDER_OBJECT              =   'Order';
    const ORDER_ITEM_OBJECT         =   'OrderItem';
    const ORDER_INVOICE_OBJECT      =   'tnw_fulfilment__OrderInvoice__c';
    const ORDER_INVOICE_ITEM_OBJECT =   'tnw_fulfilment__OrderInvoiceItem__c';
    const ORDER_SHIPMENT_OBJECT     =   'tnw_fulfilment__OrderShipment__c';
    const ORDER_SHIPMENT_ITEM_OBJECT=   'tnw_fulfilment__OrderShipmentItem__c';
    const PRODUCT_OBJECT            =   'Product';
    const INVOICE_OBJECT            =   'Invoice';
    const INVOICE_ITEM_OBJECT       =   'InvoiceItem';
    const SHIPMENT_OBJECT           =   'Shipment';
    const SHIPMENT_ITEM_OBJECT      =   'ShipmentItem';

    protected $_objects = NULL;
    protected $_oObjects = NULL;
    protected $_orderObjects = NULL;
    protected $_objectsRaw = NULL;

    public function __construct()
    {
        $this->_objects = Mage::getModel('tnw_salesforce/config_client_objects')->getAvailableObjects();
        $this->_oObjects = Mage::getModel('tnw_salesforce/config_client_objects')->getAvailableOrderObjects();
    }

    public function getObjects()
    {
        return $this->_objects;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->getOrderObjects();
    }

    public function getOrderObjects()
    {
        if (!$this->_orderObjects) {
            $this->_orderObjects = array();
            foreach ($this->_oObjects as $_obj) {
                $this->_orderObjects[] = array(
                    'label' => $_obj,
                    'value' => $_obj
                );
            }
        }
        return $this->_orderObjects;
    }

    public function getAvailableObjects()
    {
        if (!$this->_objectsRaw) {
            $this->_objectsRaw = array();
            $this->_objectsRaw[] = array(
                'label' => '',
                'value' => ''
            );
            foreach ($this->_objects as $_obj) {
                $this->_objectsRaw[] = array(
                    'label' => $_obj,
                    'value' => $_obj
                );
            }
        }
        return $this->_objectsRaw;
    }

    public function getRawObjects()
    {
        if (!$this->_objectsRaw) {
            $this->getAvailableObjects();
        }
        /* Assign Label to keys */
        $newArray = array();
        $gridArray = $this->_objectsRaw;
        array_shift($gridArray);
        foreach ($gridArray as $_item) {
            $newArray[$_item['label']] = $_item['value'];
        }
        return $newArray;
    }
}
