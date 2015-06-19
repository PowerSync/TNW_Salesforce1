<?php

/**
 * Class TNW_Salesforce_Model_Observer
 */
class TNW_Salesforce_Model_Observer
{
    const ORDER_PREFIX = 'order';
    const OPPORTUNITY_PREFIX = 'opportunity';

    protected $_nativeMenu = NULL;
    protected $_menu = NULL;
    protected $_nativeAcl = NULL;
    protected $_acl = NULL;

    protected $exportedOrders = array();

    /**
     * @return array
     */
    public function getExportedOrders()
    {
        return $this->exportedOrders;
    }

    public function adjustMenu() {

        // Update Magento admin menu
        $this->_nativeMenu = Mage::getSingleton('admin/config')
            ->getAdminhtmlConfig()
            ->getNode('menu')
        ;
        $this->_menu = $this->_nativeMenu
            ->descend('tnw_salesforce')->descend('children');

        // Update Magento ACL
        $this->_nativeAcl = Mage::getSingleton('admin/config')
            ->getAdminhtmlConfig()
            ->getNode('acl')
            ->descend('resources')
            ->descend('admin')->descend('children');

        $this->_acl = $this->_nativeAcl
            ->descend('tnw_salesforce')->descend('children');

        $_syncObject = strtolower(Mage::app()->getStore(Mage::app()->getStore()->getStoreId())->getConfig(TNW_Salesforce_Helper_Data::ORDER_OBJECT));
        $_constantName = 'static::' . strtoupper($_syncObject) . '_PREFIX';

        if (defined($_constantName)) {
            $_itemsToRetain = constant($_constantName);

            // Remove / update Order related mapping links per configuration
            $this->_updateOrderLinks(
                $this->_menu
                    ->descend('mappings')->descend('children')
                    ->descend('order_mapping')->descend('children'),
                $_itemsToRetain
            );
            $this->_updateOrderLinks(
                $this->_nativeMenu
                    ->descend('sales')->descend('children')
                    ->descend('tnw_salesforce')->descend('children')
                    ->descend('order_mappings')->descend('children'),
                $_itemsToRetain
            );

            // Remove / update Order ACL related configuration
            $this->_updateOrderLinks(
                $this->_acl
                    ->descend('mappings')->descend('children')
                    ->descend('order_mapping')->descend('children'),
                $_itemsToRetain
            );
            $this->_updateOrderLinks(
                $this->_nativeAcl
                    ->descend('sales')->descend('children')
                    ->descend('tnw_salesforce')->descend('children')
                    ->descend('order_mappings')->descend('children'),
                $_itemsToRetain
            );
        }

        // Remove Abandoned Cart links
        $this->_updateAbandonedCartLinks();

        // Remove Sync Queue link
        $this->_updateQueueLinks();

        // Remove Lead Mapping links
        $this->_updateCustomerLinks();
    }

    /**
     * Remove Lead Mapping links
     */
    protected function _updateCustomerLinks() {
        $_leverageLeads = Mage::app()->getStore(Mage::app()->getStore()->getStoreId())->getConfig(TNW_Salesforce_Helper_Data::CUSTOMER_CREATE_AS_LEAD);

        if ($this->_menu) {
            // Customer Menus
            $_customerNode = $this->_menu
                ->descend('mappings')->descend('children')
                ->descend('customer_mapping')->descend('children');
            $_customerNativeNode = $this->_nativeMenu
                ->descend('customer')->descend('children')
                ->descend('tnw_salesforce')->descend('children')
                ->descend('mappings')->descend('children');
        }
        if ($this->_acl) {
            // Customer ACL
            $_customerAclNode = $this->_acl
                ->descend('mappings')->descend('children')
                ->descend('customer_mapping')->descend('children');
            $_customerNativeAclNode = $this->_nativeAcl
                ->descend('customer')->descend('children')
                ->descend('tnw_salesforce')->descend('children')
                ->descend('mappings')->descend('children');
        }

        if (!$_leverageLeads) {
            if ($_customerNode) {
                unset($_customerNode->lead_mapping);
            }
            if ($_customerNativeNode) {
                unset($_customerNativeNode->lead_mapping);
            }
            if ($_customerAclNode) {
                unset($_customerAclNode->lead_mapping);
            }
            if ($_customerNativeAclNode) {
                unset($_customerNativeAclNode->lead_mapping);
            }
        }
    }

    /**
     * Removed Order or Opportunity mappings and status mapping links and ACL configuration
     * @param $xmlNode
     * @param $_itemsToRetain
     */
    protected function _updateOrderLinks($xmlNode, $_itemsToRetain) {
        if ($xmlNode) {
            $_keysToUnset = array();
            foreach($xmlNode as $_items) {
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
                    unset($xmlNode->{$_key});
                }
            }
        }
    }

    /**
     * Remove Sync Queue menu item
     */
    protected function _updateQueueLinks() {
        if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            unset($this->_menu->queue_sync);
            unset($this->_acl->queue_sync);
        }
    }

    /**
     * Remove Abandoned Cart Links
     */
    protected function _updateAbandonedCartLinks() {
        if (
            $this->_menu->descend('manual_sync')->descend('children')
            && !(
                Mage::helper('tnw_salesforce')->getType() == "PRO"
                && Mage::app()->getStore(Mage::app()->getStore()->getStoreId())->getConfig(
                    TNW_Salesforce_Helper_Abandoned::ABANDONED_CART_ENABLED
                )
            )
        ) {
            // Removing menu links
            unset($this->_menu->descend('manual_sync')->descend('children')->abandoned_sync);
            unset($this->_menu->descend('mappings')->descend('children')->abandoned_mapping);
            unset($this->_nativeMenu
                ->descend('sales')->descend('children')
                ->descend('tnw_salesforce')->descend('children')
                ->descend('manual_sync')->descend('children')
                ->sync_abandoned_carts
            );
            unset($this->_nativeMenu
                ->descend('sales')->descend('children')
                ->descend('tnw_salesforce')->descend('children')
                ->abandoned_mapping
            );

            // Removing ACL configuration
            unset($this->_acl->descend('manual_sync')->descend('children')->abandoned_sync);
            unset($this->_acl->descend('mappings')->descend('children')->abandoned_mapping);
            unset($this->_nativeAcl
                ->descend('sales')->descend('children')
                ->descend('tnw_salesforce')->descend('children')
                ->descend('manual_sync')->descend('children')
                ->sync_abandoned_carts
            );
            unset($this->_nativeAcl
                ->descend('sales')->descend('children')
                ->descend('tnw_salesforce')->descend('children')
                ->abandoned_mapping
            );
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
     * Show sf status message on every admin page
     *
     * @param Varien_Event_Observer $observer
     */
    public function showSfStatus(Varien_Event_Observer $observer)
    {
        /** @var Mage_Core_Controller_Varien_Action $controller */
        $controller = $observer->getEvent()->getAction();
        // Set common header on all pages for tracking purposes
        $controller->getResponse()->setHeader('X-PowerSync-Version', Mage::helper('tnw_salesforce')->getExtensionVersion(), true);

        $helper = Mage::helper('tnw_salesforce');

        $loginPage = $controller->getRequest()->getModuleName() == 'admin'
            && $controller->getRequest()->getControllerName() == 'index'
            && $controller->getRequest()->getActionName() == 'login';

        // skip if sf synchronization is disabled or we are on api config or login page
        if ($loginPage || $helper->isApiConfigurationPage() || !$helper->isWorking()) {
            return;
        }

        // show message
        if (Mage::getSingleton('core/session')->getSfNotWorking()) {
            $sfPApiUrl = Mage::helper('adminhtml')->getUrl('adminhtml/system_config/edit',
                array('section' => 'salesforce'));
            $message = 'IMPORTANT: Salesforce connection cannot be established or has expired.'
                . ' Please visit API configuration page to re-establish the connection.'
                . " <a href='$sfPApiUrl'>API configuration</a>";
            Mage::getSingleton('adminhtml/session')->addWarning($message);
        } else {
            if (!Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id')) {
                Mage::helper('tnw_salesforce/test_authentication')->mageSfAuthenticate();
            }
        }
    }

    /**
     * Method executed by tnw_sales_process_order event
     *
     * @param Varien_Event_Observer $observer
     */
    public function pushOrder(Varien_Event_Observer $observer)
    {
        $helper = Mage::helper('tnw_salesforce');

        $orderIds = $observer->getEvent()->getData('orderIds');
        //check that order has been already exported
        foreach ($orderIds as $key => $orderId) {
            if (in_array($orderId, $this->exportedOrders)) {
                $helper->log('Skipping export order ' . $orderId . '. Already exported.');
                unset($orderIds[$key]);
            } else {
                $this->exportedOrders[] = $orderId;
            }
        }
        if (empty($orderIds)) {
            return;
        }

        $type = $observer->getEvent()->getType();
        if (count($orderIds) == 1 && $type == 'bulk') {
            $type = 'salesforce';
        }

        $message = $observer->getEvent()->getMessage();
        $queueIds = $observer->getEvent()->getData('isQueue')
            ? $observer->getEvent()->getData('queueIds') : array();

        Mage::helper('tnw_salesforce')->log('Pushing Order(s) ... ');
        $this->_processOrderPush($orderIds, $message, 'tnw_salesforce/' . $type . '_order', $queueIds);
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

    public function prepareQuotesForSync($observer)
    {
        if (!Mage::helper('tnw_salesforce/abandoned')->isEnabled()) {
            return false;
        }

        /** @var $quote Mage_Sales_Model_Quote */
        $quote = $observer->getEvent()->getQuote();
        if (!$quote) {
            return false;
        }

        $quote->setSfSyncForce(1);

    }

    /**
     * @comment change related Opportunity status to CloseWon
     * @param $observer
     */
    public function updateAbandonedStatus($observer)
    {
        $orders = array_values($observer->getEvent()->getData('data'));
        $result = $observer->getEvent()->getResult();
        $opportunityField = $observer->getEvent()->getField();
        if (!$opportunityField) {
            $opportunityField = 'OpportunityId';
        }

        $abandonedOpportunities = array();

        foreach ($orders as $key => $order) {
            if (property_exists($order, $opportunityField)) {
                $abandonedOpportunities[] = $order->$opportunityField;
            }
        }

        if (!empty($abandonedOpportunities)) {
            /**
             * @var $collection TNW_Salesforce_Model_Api_Entity_Resource_Opportunity_Collection
             */
            $collection = Mage::getModel('tnw_salesforce/api_entity_opportunity')->getCollection();
            $collection->addFieldToFilter('Id', array('in' => $abandonedOpportunities));

            $collection->setDataToAll('StageName', 'Closed Won');
            $collection->save();
        }

    }
}