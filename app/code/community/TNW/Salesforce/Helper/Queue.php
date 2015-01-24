<?php

class TNW_Salesforce_Helper_Queue extends Mage_Core_Helper_Abstract
{
    protected $_prefix = NULL;
    protected $_itemIds = array();

    public function processItems($itemIds = array())
    {
        $this->_prefix = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix();

        if (count($itemIds) > 50 && Mage::helper('tnw_salesforce')->isAdmin()) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper("tnw_salesforce")->__("Please synchronize no more then 50 item at one time"));
            return false;
        }

        $this->_itemIds = $itemIds;

        try {
            $this->_processProducts();

            $this->_processCustomers();

            $this->_processWebsites();

            $this->_processOrders();

            $this->_processCustomObjects();

        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Process Products
     */
    protected function _processProducts() {
        $_type = 'Product';
        $_module = 'tnw_salesforce/bulk_product';

        $this->_synchronize($_type, $_module);
    }

    /**
     * Process Customers
     */
    protected function _processCustomers() {
        $_type = 'Customer';
        $_module = 'tnw_salesforce/bulk_customer';

        $this->_synchronize($_type, $_module);
    }

    /**
     * Process Website
     */
    protected function _processWebsites() {
        $_type = 'Website';
        $_module = 'tnw_salesforce/bulk_website';

        $this->_synchronize($_type, $_module);
    }

    /**
     * Process Order
     */
    protected function _processOrders() {
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
     * Used for customization
     */
    protected function _processCustomObjects() {

    }

    /**
     * @param $_type
     * @return mixed
     * Load queued enteties
     */
    protected function _loadQueue($_type) {
        $_collection = Mage::getModel('tnw_salesforce/queue_storage')->getCollection()
            ->addSftypeToFilter($_type)
            ->addStatusNoToFilter('sync_running');
        if (count($this->_itemIds) > 0){
            $_collection->getSelect()->where('id IN (?)', $this->_itemIds);
        }

        return $_collection;
    }

    /**
     * @param $_type
     * Process selected queue items
     */
    protected function _synchronize($_type, $_module) {

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

            if ($_type == 'Order') {
                $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());

                Mage::dispatchEvent(
                    'tnw_sales_process_' . $_syncType,
                    array(
                        'orderIds'      => $_objectIdSet,
                        'message'       => NULL,
                        'type'   => 'bulk'
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
                }
            }

            if (!empty($_idSet)) {
                Mage::helper('tnw_salesforce')->log("INFO: " . $_type . " total synced: " . count($_idSet));
                Mage::helper('tnw_salesforce')->log("INFO: removing synced rows from mysql table...");
                Mage::getModel('tnw_salesforce/localstorage')->deleteObject($_idSet);
            }
        } else {
            Mage::helper('tnw_salesforce')->log("ERROR: Salesforce connection failed");
            return false;
        }
    }
}