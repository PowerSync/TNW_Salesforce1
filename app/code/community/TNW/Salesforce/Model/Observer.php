<?php

/**
 * Class TNW_Salesforce_Model_Observer
 */
class TNW_Salesforce_Model_Observer
{
    public function adjustMenu() {
        // Update Magento admin menu
        $_menu = Mage::getSingleton('admin/config')
            ->getAdminhtmlConfig()
            ->getNode('menu')
            ->descend('system')
            ->descend('children')
            ->descend('salesforce')
            ->descend('children')
        ;

        // Update Magento ACL
        $_acl = Mage::getSingleton('admin/config')
            ->getAdminhtmlConfig()
            ->getNode('acl')
            ->descend('resources')
            ->descend('admin')
            ->descend('children')
            ->descend('system')
            ->descend('children')
            ->descend('salesforce')
            ->descend('children')
        ;

        $_syncObject = strtolower(Mage::app()->getStore(Mage::app()->getStore()->getStoreId())->getConfig(TNW_Salesforce_Helper_Data::ORDER_OBJECT));
        $_leverageLeads = Mage::app()->getStore(Mage::app()->getStore()->getStoreId())->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_CREATE_AS_LEAD);

        if ($_menu) {
            $_orderNode = $_menu->descend('order_mapping')->descend('children');
            $_customerNode = $_menu->descend('customer_mapping')->descend('children');
        }
        if ($_acl) {
            $_orderAclNode = $_acl->descend('order_mapping')->descend('children');
            $_customerAclNode = $_acl->descend('customer_mapping')->descend('children');
        }

        if ($_orderAclNode) {
            unset($_orderAclNode->{$_syncObject . '_mapping'});
            unset($_orderAclNode->{$_syncObject . '_cart_mapping'});
        }

        if ($_orderNode) {
            unset($_orderNode->{$_syncObject . '_mapping'});
            unset($_orderNode->{$_syncObject . '_cart_mapping'});
        }

        if (!$_leverageLeads) {
            if ($_customerNode) {
                unset($_customerNode->lead_mapping);
            }
            if ($_customerAclNode) {
                unset($_customerAclNode->lead_mapping);
            }
        }
    }

    /**
     * Check for extension update news
     *
     * @param Varien_Event_Observer $observer
     */
    public function preDispatch(Varien_Event_Observer $observer)
    {
        if (
            Mage::helper('tnw_salesforce')->isEnabled()
            && Mage::helper('tnw_salesforce')->getObjectSyncType() == 'sync_type_realtime'
        ) {
            try {
                //Mage::getModel('tnw_salesforce/feed')->checkUpdate();
                Mage::getModel('tnw_salesforce/imports_bulk')->process();
            } catch (Exception $e) {
                // silently ignore
            }
        }
    }

    /**
     * this model method calls our facade pattern class method mageAdminLoginEvent()
     *
     * @param Varien_Event_Observer $observer
     * @return mixed
     */
    public function mageLoginEventCall(Varien_Event_Observer $observer)
    {
        return Mage::helper('tnw_salesforce/test_authentication')->mageSfAuthenticate();
    }

    /**
     * show sf status message on every admin page
     *
     * @return bool
     */
    public function showSfStatus(Varien_Event_Observer $observer)
    {
        // Set common header on all pages for tracking purposes
        Mage::app()->getFrontController()->getResponse()->setHeader('X-PowerSync-Version', Mage::helper('tnw_salesforce')->getExtensionVersion(), true);

        // skip if sf synchronization is disabled or we are on api config page

        $urlParamSet = Mage::app()->getRequest()->getParams();
        if (!Mage::helper('tnw_salesforce')->isWorking()
            || Mage::app()->getRequest()->getControllerName() == 'index'
            // || (Mage::app()->getRequest()->getControllerName() == 'system_config' && $urlParamSet['section'] == 'salesforce')
        ) {
            return false;
        }

        // show message
        $sfNotWorking = $Data = Mage::getSingleton('core/session')->getSfNotWorking();
        if ($sfNotWorking) {
            $sfPApiUrl = Mage::helper("adminhtml")->getUrl("adminhtml/system_config/edit/section/salesforce");
            $message = "IMPORTANT: Salesforce connection cannot be established or has expired. Please visit API configuration page to re-establish the connection. <a href='$sfPApiUrl'>API configuration</a>";
            // Only show warnings and messages if in the Admin Panel
            if (Mage::helper('tnw_salesforce')->isAdmin()) {
                Mage::getSingleton('core/session')->addWarning($message);
            }
        } else {
            if (!Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id')) {
                Mage::helper('tnw_salesforce/test_authentication')->mageSfAuthenticate();
            }
        }

        return true;
    }

    public function pushOrder(Varien_Event_Observer $observer) {
        $_orderIds = $observer->getEvent()->getData('orderIds');
        $_message = $observer->getEvent()->getMessage();
        $_type = $observer->getEvent()->getType();
        $_isQueue = $observer->getEvent()->getData('isQueue');
        $_queueIds = ($_isQueue) ? $observer->getEvent()->getData('queueIds') : array();

        if (count($_orderIds) == 1 && $_type == 'bulk') {
            $_type = 'salesforce';
        }

        Mage::helper('tnw_salesforce')->log('Pushing Order(s) ... ');
        $this->_processOrderPush($_orderIds, $_message, 'tnw_salesforce/' . $_type . '_order', $_queueIds);
    }

    public function pushOpportunity(Varien_Event_Observer $observer) {
        $_orderIds = $observer->getEvent()->getData('orderIds');
        $_message = $observer->getEvent()->getMessage();
        $_type = $observer->getEvent()->getType();
        $_isQueue = $observer->getEvent()->getData('isQueue');
        $_queueIds = ($_isQueue) ? $observer->getEvent()->getData('queueIds') : array();

        if (count($_orderIds) == 1 && $_type == 'bulk') {
            $_type = 'salesforce';
        }

        Mage::helper('tnw_salesforce')->log('Pushing Opportunities ... ');
        $this->_processOrderPush($_orderIds, $_message, 'tnw_salesforce/' . $_type . '_opportunity', $_queueIds);
    }

    protected function _processOrderPush($_orderIds, $_message, $_model, $_queueIds) {
        $manualSync = Mage::helper($_model);
        $manualSync->setSalesforceServerDomain(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url'));
        $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));
        if ($_message === NULL) {
            $manualSync->setIsCron(true);
        }
        $_ids = (count($_orderIds) == 1) ? $_orderIds[0] : $_orderIds;

        if ($manualSync->reset()) {
            if ($manualSync->massAdd($_ids)) {
                $res = $manualSync->process('full');
                if ($res) {
                    // Update queue
                    if (!empty($_queueIds)) {
                        $_results = $manualSync->getSyncResults();
                        $_alternativeKeys = $manualSync->getAlternativeKeys();
                        Mage::getModel('tnw_salesforce/localstorage')->updateQueue($_orderIds, $_queueIds, $_results, $_alternativeKeys);
                    }

                    if ($_message) {
                        Mage::helper('tnw_salesforce')->log($_message);
                        if (Mage::helper('tnw_salesforce')->isAdmin()) {
                            Mage::getSingleton('adminhtml/session')->addSuccess($_message);
                        }
                    }
                }
            }
        } else {
            Mage::helper('tnw_salesforce')->log("Salesforce connection could not be established!");
            if ($_message === NULL) {
                Mage::throwException('Salesforce connection failed');
            }
        }
    }

    public function updateOpportunity(Varien_Event_Observer $observer) {
        $_order = $observer->getEvent()->getData('order');

        Mage::helper('tnw_salesforce')->log('Updating Opportunity Status ... ');
        if ($_order && is_object($_order)) {
            Mage::helper('tnw_salesforce/salesforce_opportunity')->updateStatus($_order);
        }
    }

    public function updateOrder(Varien_Event_Observer $observer) {
        $_order = $observer->getEvent()->getData('order');

        Mage::helper('tnw_salesforce')->log('Updating Order Status ... ');
        if ($_order && is_object($_order)) {
            Mage::helper('tnw_salesforce/salesforce_order')->updateStatus($_order);
        }
    }
}