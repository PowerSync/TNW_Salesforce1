<?php

class TNW_Salesforce_Helper_Queue extends Mage_Core_Helper_Abstract
{
    const UPDATE_LIMIT = 5000;

    protected $_prefix = NULL;
    protected $_itemIds = array();

    public function processItems($itemIds = array())
    {
        if (count($itemIds) > 50 && Mage::helper('tnw_salesforce')->isAdmin()) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper("tnw_salesforce")->__("Please synchronize no more then 50 item at one time"));
            return false;
        }

        set_time_limit(0);

        try {
            if (empty($itemIds)) {
                $this->_processProducts();

                $this->_processCustomers();

                $this->_processWebsites();

                $this->_processAbandoned();

                $this->_processOrders();

                $this->_processInvoices();

                $this->_processCustomObjects();
            } else {
                $this->_itemIds = $itemIds;
                $this->_synchronizePreSet();
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Process Products
     */
    protected function _processProducts()
    {
        $_type = 'Product';
        $_module = 'tnw_salesforce/bulk_product';

        $this->_synchronize($_type, $_module);
    }

    /**
     * Process Customers
     */
    protected function _processCustomers()
    {
        $_type = 'Customer';
        $_module = 'tnw_salesforce/bulk_customer';

        $this->_synchronize($_type, $_module);
    }

    /**
     * Process Website
     */
    protected function _processWebsites()
    {
        $_type = 'Website';
        $_module = 'tnw_salesforce/bulk_website';

        $this->_synchronize($_type, $_module);
    }

    /**
     * Process Order
     */
    protected function _processInvoices()
    {
        $_type = 'Invoice';
        // Allow Powersync to overwite fired event for customizations
        $_object = new Varien_Object(array('object_type' => TNW_Salesforce_Model_Order_Invoice_Observer::OBJECT_TYPE));
        Mage::dispatchEvent('tnw_salesforce_set_invoice_object', array('sf_object' => $_object));

        $_module = 'tnw_salesforce/bulk_' . $_object->getObjectType();

        $total = Mage::getModel('tnw_salesforce/localstorage')->countObjectBySfType(array(
            'Product',
            'Customer',
            'Website',
            'Order',
        ));

        if ($total > 0) {
            Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper("tnw_salesforce")->__("SKIPPING INVOICES: Not all dependencies are synchronized"));
            return false;
        }

        $this->_synchronize($_type, $_module);
    }

    /**
     * Process Order
     */
    protected function _processOrders()
    {
        $_type = 'Order';
        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
        $_module = 'tnw_salesforce/bulk_' . $_syncType;

        $total = Mage::getModel('tnw_salesforce/localstorage')->countObjectBySfType(array(
            'Product',
            'Customer',
            'Website',
        ));

        if ($total > 0) {
            Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper("tnw_salesforce")->__("SKIPPING ORDERS: Not all products, websites and / or customers are synchronized"));
            return false;
        }

        $this->_synchronize($_type, $_module);
    }

    /**
     * Process Abandoned carts
     */
    protected function _processAbandoned()
    {
        $_type = 'Abandoned';
        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getAbandonedObject());
        $_module = 'tnw_salesforce/bulk_abandoned_' . $_syncType;

        $total = Mage::getModel('tnw_salesforce/localstorage')->countObjectBySfType(array(
            'Product',
            'Customer',
            'Website',
        ));

        if ($total > 0) {
            Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper("tnw_salesforce")->__("SKIPPING Abandoned: Not all products, websites and / or customers are synchronized"));
            return false;
        }

        $this->_synchronize($_type, $_module);
    }

    /**
     * Used for customization
     */
    protected function _processCustomObjects()
    {

    }

    /**
     * @param $_type
     * @return mixed
     * Load queued enteties
     */
    protected function _loadQueue($_type = NULL)
    {
        $_collection = Mage::getModel('tnw_salesforce/queue_storage')->getCollection()
            ->addStatusNoToFilter('sync_running')
            ->addStatusNoToFilter('success')
            ->setOrder('status', 'ASC');
        if ($_type) {
            $_collection->addSftypeToFilter($_type);
        }

        if (count($this->_itemIds) > 0) {
            $_collection->getSelect()->where('id IN (?)', $this->_itemIds);
        }

        return $_collection;
    }

    protected function _synchronizePreSet()
    {
        $_collection = $this->_loadQueue();

        $_idSet = array();
        $_objectIdSet = array();
        $_objectType = array();

        foreach ($_collection as $item) {
            $_idSet[] = $item->getData('id');
            $_objectIdSet[] = $item->getData('object_id');
            if (!in_array($item->getData('sf_object_type'), $_objectType)) {
                $_objectType[] = $item->getData('sf_object_type');
            }
        }

        if (count($_objectType) > 1) {
            Mage::getModel('tnw_salesforce/tool_log')->saveError("ERROR: Synchronization of multiple record types is not supported yet, try synchronizing one record type.");
            return false;
        }

        $_type = $_objectType[0];

        if (!empty($_objectIdSet)) {
            // set status to 'sync_running'
            Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($_idSet);

            if ($_type == 'Order' || $_type == 'Invoice') {
                if ($_type == 'Invoice') {
                    // Allow Powersync to overwite fired event for customizations
                    $_object = new Varien_Object(array('object_type' => TNW_Salesforce_Model_Order_Invoice_Observer::OBJECT_TYPE));
                    Mage::dispatchEvent('tnw_salesforce_set_invoice_object', array('sf_object' => $_object));

                    $_syncType = $_object->getObjectType();
                    $_idPrefix = 'invoice';
                } else {
                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
                    $_idPrefix = 'order';
                }

                Mage::dispatchEvent(
                    'tnw_sales_process_' . $_syncType,
                    array(
                        $_idPrefix . 'Ids' => $_objectIdSet,
                        'message' => NULL,
                        'type' => 'bulk',
                        'isQueue' => true,
                        'queueIds' => $_idSet
                    )
                );
            } elseif ($_type == 'Invoice') {
                $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());

                Mage::dispatchEvent(
                    'tnw_sales_process_' . $_syncType,
                    array(
                        'orderIds' => $_objectIdSet,
                        'message' => NULL,
                        'type' => 'bulk',
                        'isQueue' => true,
                        'queueIds' => $_idSet
                    )
                );
            } else {
                $_module = 'tnw_salesforce/bulk_' . strtolower($_type);
                $_getAlternativeKey = NULL;

                $manualSync = Mage::helper($_module);
                if ($manualSync->reset()) {
                    $manualSync->setIsCron(true);
                    $manualSync->setSalesforceServerDomain(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url'));
                    $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

                    Mage::getModel('tnw_salesforce/tool_log')->saveNotice('################################## manual processing from queue for ' . $_type . ' started ##################################');
                    $manualSync->massAdd($_objectIdSet);

                    $manualSync->process();
                    Mage::getModel('tnw_salesforce/tool_log')->saveNotice('################################## manual processing from queue for ' . $_type . ' finished ##################################');

                    // Update Queue
                    $_results = $manualSync->getSyncResults();
                    $_alternativeKeys = ($_getAlternativeKey) ? $manualSync->getAlternativeKeys() : array();
                    Mage::getModel('tnw_salesforce/localstorage')->updateQueue($_objectIdSet, $_idSet, $_results, $_alternativeKeys);
                }
            }

            if (!empty($_idSet)) {
                Mage::getModel('tnw_salesforce/tool_log')->saveNotice("INFO: " . $_type . " total synced: " . count($_idSet));
                Mage::getModel('tnw_salesforce/tool_log')->saveNotice("INFO: removing synced rows from mysql table...");
            }
        } else {
            Mage::helper('tnw_salesforce')->log("ERROR: Salesforce connection failed");
            return false;
        }
    }

    /**
     * @param $_type
     * Process selected queue items
     */
    protected function _synchronize($_type, $_module, $_getAlternativeKey = false)
    {

        $_collection = $this->_loadQueue($_type);

        $_idSet = array();
        $_objectIdSet = array();

        foreach ($_collection as $item) {
            $_idSet[] = $item->getData('id');
            $_objectIdSet[] = $item->getData('object_id');
        }

        if (!empty($_objectIdSet)) {
            // set status to 'sync_running'
            Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($_idSet);

            if ($_type == 'Order' || $_type == 'Abandoned') {
                if ($_type == 'Abandoned') {
                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getAbandonedObject());
                } else {
                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
                }

                Mage::dispatchEvent(
                    'tnw_sales_process_' . $_syncType,
                    array(
                        'orderIds' => $_objectIdSet,
                        'message' => NULL,
                        'type' => 'bulk',
                        'isQueue' => true,
                        'queueIds' => $_idSet,
                        'object_type' => $_type
                    )
                );
            } else {
                $manualSync = Mage::helper($_module);
                if ($manualSync->reset()) {
                    $manualSync->setIsCron(true);
                    $manualSync->setSalesforceServerDomain(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url'));
                    $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

                    Mage::helper('tnw_salesforce')->log('################################## manual processing from queue for ' . $_type . ' started ##################################');
                    $manualSync->massAdd($_objectIdSet);

                    $manualSync->process();
                    Mage::helper('tnw_salesforce')->log('################################## manual processing from queue for ' . $_type . ' finished ##################################');

                    // Update Queue
                    $_results = $manualSync->getSyncResults();
                    $_alternativeKeys = ($_getAlternativeKey) ? $manualSync->getAlternativeKeys() : array();
                    Mage::getModel('tnw_salesforce/localstorage')->updateQueue($_objectIdSet, $_idSet, $_results, $_alternativeKeys);
                }
            }

            if (!empty($_idSet)) {
                Mage::helper('tnw_salesforce')->log("INFO: " . $_type . " total synced: " . count($_idSet));
                Mage::helper('tnw_salesforce')->log("INFO: removing synced rows from mysql table...");
            }
        } else {
            Mage::helper('tnw_salesforce')->log("ERROR: Salesforce connection failed");
            return false;
        }
    }

    /**
     * @TODO change this method calling to the addObject localstorage method
     * @deprecated, exists modules compatibility for
     * @param array $itemIds
     * @param null $_sfObject
     * @param null $_mageModel
     */
    public function prepareRecordsToBeAddedToQueue($itemIds = array(), $_sfObject = NULL, $_mageModel = NULL)
    {
        if (empty($itemIds) || !$_sfObject || !$_mageModel) {
            Mage::getSingleton('adminhtml/session')->addError('Could not add records to the queue!');
        }


        $localstorage = Mage::getModel('tnw_salesforce/localstorage');

        $localstorage->addObject($itemIds, $_sfObject, $_mageModel);

    }
}