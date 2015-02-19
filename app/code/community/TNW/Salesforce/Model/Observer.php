<?php

/**
 * Class TNW_Salesforce_Model_Observer
 */
class TNW_Salesforce_Model_Observer
{
    const ORDER_PREFIX = 'order';
    const OPPORTUNITY_PREFIX = 'opportunity';

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

        $_constantName = 'static::' . strtoupper($_syncObject) . '_PREFIX';
        if (defined($_constantName)) {
            $_itemsToRetain = constant($_constantName);

            if ($_menu) {
                $_orderNode = $_menu->descend('order_mapping')->descend('children');
                $_customerNode = $_menu->descend('customer_mapping')->descend('children');
            }
            if ($_acl) {
                $_orderAclNode = $_acl->descend('order_mapping')->descend('children');
                $_customerAclNode = $_acl->descend('customer_mapping')->descend('children');
            }

            if ($_orderAclNode) {
                $_keysToUnset = array();
                foreach($_orderAclNode as $_items) {
                    foreach($_items as $_key => $_item) {
                        if (
                            $_key != $_itemsToRetain . '_mapping'
                            && $_key != $_itemsToRetain . '_cart_mapping'
                            && $_key != 'status_mapping'
                        ) {
                            $_keysToUnset[] =  $_key;
                        }
                    }
                }
                if (!empty($_keysToUnset)) {
                    foreach($_keysToUnset as $_key) {
                        unset($_orderAclNode->{$_key});
                    }
                }
            }

            if ($_orderNode) {
                $_keysToUnset = array();
                foreach($_orderNode as $_items) {
                    foreach($_items as $_key => $_item) {
                        if (
                            $_key != $_itemsToRetain . '_mapping'
                            && $_key != $_itemsToRetain . '_cart_mapping'
                            && $_key != 'status_mapping'
                        ) {
                            $_keysToUnset[] =  $_key;
                        }
                    }
                }
                if (!empty($_keysToUnset)) {
                    foreach($_keysToUnset as $_key) {
                        unset($_orderNode->{$_key});
                    }
                }
            }
        }

        // Remove Sync Queue menu item
        if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            unset($_menu->queue_sync);
            unset($_acl->queue_sync);
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

        $_objectType = $observer->getEvent()->getData('object_type');

        $_queueIds = ($_isQueue) ? $observer->getEvent()->getData('queueIds') : array();

        if (count($_orderIds) == 1 && $_type == 'bulk') {
            $_type = 'salesforce';
        }

        Mage::helper('tnw_salesforce')->log('Pushing Order(s) ... ');
        $this->_processOrderPush($_orderIds, $_message, 'tnw_salesforce/' . $_type . '_order', $_queueIds);
    }

    public function pushOpportunity(Varien_Event_Observer $observer) {

        $_objectType = strtolower($observer->getEvent()->getData('object_type'));

        $_orderIds = $observer->getEvent()->getData('orderIds');

        if ($_objectType == 'abandoned' && empty($_orderIds)) {
            $_orderIds = $observer->getEvent()->getData('ids');
        }

        $_message = $observer->getEvent()->getMessage();
        $_type = $observer->getEvent()->getType();
        $_isQueue = $observer->getEvent()->getData('isQueue');

        $_queueIds = ($_isQueue) ? $observer->getEvent()->getData('queueIds') : array();

        if (count($_orderIds) == 1 && $_type == 'bulk') {
            $_type = 'salesforce';
        }

        Mage::helper('tnw_salesforce')->log('Pushing Opportunities ... ');

        if ($_objectType == 'abandoned') {
            $this->_processOrderPush($_orderIds, $_message, 'tnw_salesforce/' . $_type . '_abandoned_opportunity', $_queueIds);
        } else {
            $this->_processOrderPush($_orderIds, $_message, 'tnw_salesforce/' . $_type . '_opportunity', $_queueIds);
        }
    }

    protected function _processOrderPush($_orderIds, $_message, $_model, $_queueIds) {
        /**
         * @var $manualSync TNW_Salesforce_Helper_Salesforce_Abandoned_Opportunity|TNW_Salesforce_Helper_Salesforce_Opportunity|TNW_Salesforce_Helper_Salesforce_Order
         */
        $manualSync = Mage::helper($_model);
        $manualSync->setSalesforceServerDomain(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_url'));
        $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

        $_ids = (count($_orderIds) == 1) ? $_orderIds[0] : $_orderIds;

        if ($manualSync->reset()) {
            if ($manualSync->massAdd($_ids)) {
                if ($_message === NULL) {
                    $manualSync->setIsCron(true);
                }
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

    public function updateOrderStatusForm($observer) {
        $_form = $observer->getForm();

        if (Mage::helper('tnw_salesforce')->isWorking()) {
            $fieldset = $_form->addFieldset(
                'sf_fieldset',
                array(
                    'legend' => Mage::helper('tnw_salesforce')->__('Salesforce Status')
                ),
                'base_fieldset'
            );

            $_syncType = strtolower(Mage::helper('tnw_salesforce')->getOrderObject());
            $sfFields = array();
            $_sfData = Mage::helper('tnw_salesforce/salesforce_data');
            $sfFields[] = array(
                'value' => '',
                'label' => 'Choose Salesforce Status ...'
            );

            if ($_syncType == 'order') {
                $states = $_sfData->getPicklistValues('Order', 'Status');
                if (!is_array($states)) {
                    $states = array();
                }
                foreach ($states as $key => $field) {
                    $sfFields[] = array(
                        'value' => $field->label,
                        'label' => $field->label
                    );
                }
                $_field = 'sf_order_status';
            } else {
                $states = $_sfData->getStatus('Opportunity');
                if (!is_array($states)) {
                    $states = array();
                }
                foreach ($states as $key => $field) {
                    $sfFields[] = array(
                        'value' => $field->MasterLabel,
                        'label' => $field->MasterLabel
                    );
                }
                $_field = 'sf_opportunity_status_code';
            }

            $fieldset->addField($_field, 'select',
                array(
                    'name' => $_field,
                    'label' => Mage::helper('tnw_salesforce')->__('Status'),
                    'class' => 'required-entry',
                    'required' => false,
                    'values' => $sfFields
                )
            );

            Mage::dispatchEvent('tnw_salesforce_sales_order_status_prepare_form_update', array('form' => $_form, 'fieldset' => $fieldset));
        }
    }

    public function saveSfStatus($observer) {
        $statusCode = $observer->getStatus();
        $_request = $observer->getRequest();

        $orderStatusMapping = Mage::getModel('tnw_salesforce/order_status');
        $collection = Mage::getModel('tnw_salesforce/order_status')->getCollection();
        $collection->getSelect()
            ->where("main_table.status = ?", $statusCode);
        foreach ($collection as $_item) {
            $orderStatusMapping->load($_item->status_id);
        }
        $orderStatusMapping->setStatus($statusCode);

        foreach (Mage::getModel('tnw_salesforce/config_order_status')->getAdditionalFields() as $_field) {
            if ($_request->getParam($_field)) {
                $orderStatusMapping->setData($_field, $_request->getParam($_field));

            }
        }
        $orderStatusMapping->save();
    }
}