<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Observer
{
    const ORDER_PREFIX = 'order';
    const OPPORTUNITY_PREFIX = 'opportunity';

    /** @var null|Varien_Simplexml_Element */
    protected $_nativeMenu = NULL;

    /** @var null|Varien_Simplexml_Element */
    protected $_menu = NULL;

    /** @var null|Varien_Simplexml_Element */
    protected $_nativeAcl = NULL;

    /** @var null|Varien_Simplexml_Element */
    protected $_acl = NULL;

    /**
     * @var array
     */
    protected $exportedOrders = array();

    /**
     * @var array
     */
    protected $exportedOpportunity = array(
        'opportunity' => array(),
        'abandoned' => array(),
    );

    /**
     * @return array
     */
    public function getExportedOpportunity()
    {
        return $this->exportedOpportunity;
    }

    /**
     * @param array $exportedOpportunity
     */
    public function setExportedOpportunity($exportedOpportunity)
    {
        $this->exportedOpportunity = $exportedOpportunity;

        return $this;
    }

    /**
     * @return array
     */
    public function getExportedOrders()
    {
        return $this->exportedOrders;
    }

    /**
     * @return array
     */
    public function setExportedOrders($exportedOrders)
    {
        $this->exportedOrders = $exportedOrders;

        return $this;
    }

    /**
     * @param $menu Varien_Simplexml_Element
     * @return $this
     */
    public function checkConfigCondition($menu)
    {
        $_attributes = array_map(array($menu, 'xpath'), array(
            'ifconfig' => '//*[@ifconfig]',
            'ifhelper' => '//*[@ifhelper]'
        ));

        /** @var Varien_Simplexml_Element[] $_nodes */
        foreach ($_attributes as $_attribute => $_nodes) {
            if (empty($_nodes)) {
                continue;
            }

            foreach ($_nodes as $_node) {
                $attributes = (array)$_node->attributes();
                if (!isset($attributes['@attributes'][$_attribute])) {
                    continue;
                }

                $_value = $attributes['@attributes'][$_attribute];
                switch($_attribute) {
                    case 'ifconfig':
                        if (Mage::getStoreConfig($_value)) {
                            continue 2;
                        }
                        break;

                    case 'ifhelper':
                        list($helper, $method) = explode('::', $_value, 2);
                        $helper = Mage::helper($helper);
                        if (!method_exists($helper, $method)) {
                            continue 2;
                        }

                        if ((int)call_user_func(array($helper, $method))) {
                            continue 2;
                        }
                        break;
                }

                $dom = dom_import_simplexml($_node);
                $dom->parentNode->removeChild($dom);
            }
        }

        return $this;
    }

    /**
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function loadConfig(Varien_Event_Observer $observer)
    {
        $sections = $observer->getEvent()->getConfig()->getNode('sections');
        $this->checkConfigCondition($sections);
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function checkLayout(Varien_Event_Observer $observer)
    {
        $node = $observer->getEvent()->getLayout()->getNode();
        $this->checkConfigCondition($node);
    }

    public function adjustMenu()
    {
        try {
            // Update Magento admin menu
            $this->_nativeMenu = Mage::getSingleton('admin/config')
                ->getAdminhtmlConfig()
                ->getNode('menu');
            $this->_menu = $this->_nativeMenu
                ->descend('tnw_salesforce')->descend('children');

            $this->checkConfigCondition($this->_nativeMenu);

            // Update Magento ACL
            $this->_nativeAcl = Mage::getSingleton('admin/config')
                ->getAdminhtmlConfig()
                ->getNode('acl')
                ->descend('resources')
                ->descend('admin')->descend('children');
            $this->_acl = $this->_nativeAcl
                ->descend('tnw_salesforce')->descend('children');

            $this->checkConfigCondition($this->_nativeAcl);
        } catch (Exception $e) {
            // SKIP: to deal with caching during the upgrade
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
     * Fix admin user acl after login
     */
    public function fixAcl()
    {
        $session = Mage::getSingleton('admin/session');
        /** @var Mage_Admin_Model_User $user */
        $user = $session->getUser();
        $acl = $session->getAcl();
        $aclRole = $user->getAclRole();

        //allow order status page
        if ($session->isAllowed('tnw_salesforce/mappings/order_mapping/status_mapping')
            || $session->isAllowed('sales/tnw_salesforce/order_mappings/status_mapping')
        ) {
            $acl->allow($user->getAclRole(), 'admin/system/order_statuses');
        }

        //allow api config - if not allowed in system
        if ($session->isAllowed('tnw_salesforce/setup/api_config')) {
            $acl->allow($aclRole, 'admin/system/config');
            $acl->allow($aclRole, 'admin/system/config/salesforce');
        }

        //allow order configuration page - if not allowed in system
        if ($session->isAllowed('sales/tnw_salesforce/configuration')
            || $session->isAllowed('tnw_salesforce/setup/order_config')
        ) {
            $acl->allow($aclRole, 'admin/system/config');
            $acl->allow($aclRole, 'admin/system/config/salesforce_order');
        }

        //allow product configuration page - if not allowed in system
        if ($session->isAllowed('catalog/tnw_salesforce/configuration')
            || $session->isAllowed('tnw_salesforce/setup/order_product')
        ) {
            $acl->allow($aclRole, 'admin/system/config');
            $acl->allow($aclRole, 'admin/system/config/salesforce_product');
        }

        //allow customer configuration page - if not allowed in system
        if ($session->isAllowed('customer/tnw_salesforce/configuration')
            || $session->isAllowed('tnw_salesforce/setup/customer_config')
        ) {
            $acl->allow($aclRole, 'admin/system/config');
            $acl->allow($aclRole, 'admin/system/config/salesforce_customer');
        }
    }

    /**
     * Show sf status message on every admin page
     *
     * @param Varien_Event_Observer $observer
     * @throws Exception
     */
    public function showSfStatus(Varien_Event_Observer $observer)
    {
        /** @var Mage_Core_Controller_Varien_Action $controller */
        $controller = $observer->getEvent()->getAction();
        // Set common header on all pages for tracking purposes
        $controller->getResponse()->setHeader('X-PowerSync-Version', Mage::helper('tnw_salesforce')->getExtensionVersion(), true);

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');
        if ($helper->isLoginPage() || $helper->isApiConfigurationPage()) {
            return;
        }

        /** @var Mage_Core_Model_Website $website */
        foreach (Mage::helper('tnw_salesforce/config')->getWebsitesDifferentConfig() as $website) {
            Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function() use($helper) {
                if (!$helper->isEnabled()) {
                    return;
                }

                if (!TNW_Salesforce_Helper_Test_License::isValidate()) {
                    return;
                }

                Mage::helper('tnw_salesforce/test_authentication')
                    ->mageSfAuthenticate();
            });
        }
    }

    /**
     * Method executed by tnw_salesforce_order_process event
     *
     * @param Varien_Event_Observer $observer
     * @deprecated
     */
    //TODO: delete this method
    public function pushOrder(Varien_Event_Observer $observer)
    {
        $orderIds = $observer->getEvent()->getData('orderIds');
        //check that order has been already exported
        foreach ($orderIds as $key => $orderId) {
            if (in_array($orderId, $this->exportedOrders)) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Skipping export order ' . $orderId . '. Already exported.');
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

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Pushing Order(s) ... ');
        $this->_processOrderPush($orderIds, $message, 'tnw_salesforce/' . $type . '_order', $queueIds);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @deprecated
     */
    //TODO: delete this method
    public function pushOpportunity(Varien_Event_Observer $observer)
    {
        $_objectType = strtolower($observer->getEvent()->getData('object_type'));
        if (!isset($this->exportedOpportunity[$_objectType])) {
            $this->exportedOpportunity[$_objectType] = array();
        }

        $_orderIds = $observer->getEvent()->getData('orderIds');
        //check that order has been already exported
        foreach ($_orderIds as $key => $orderId) {
            if (!in_array($orderId, $this->exportedOpportunity[$_objectType])) {
                $this->exportedOpportunity[$_objectType][] = $orderId;
                continue;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Skipping export opportunity ' . $orderId . '. Already exported.');
            unset($_orderIds[$key]);
        }

        if (empty($_orderIds)) {
            return;
        }

        $_type = $observer->getEvent()->getType();
        if (count($_orderIds) == 1 && $_type == 'bulk') {
            $_type = 'salesforce';
        }

        $_message = $observer->getEvent()->getMessage();
        $_queueIds = $observer->getEvent()->getData('isQueue')
            ? $observer->getEvent()->getData('queueIds') : array();

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Pushing Opportunities ... ');

        if ($_objectType == 'abandoned') {
            $this->_processOrderPush($_orderIds, $_message, 'tnw_salesforce/' . $_type . '_abandoned_opportunity', $_queueIds);
        } else {
            $this->_processOrderPush($_orderIds, $_message, 'tnw_salesforce/' . $_type . '_opportunity', $_queueIds);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     * @deprecated
     */
    //TODO: delete this method
    public function pushInvoice(Varien_Event_Observer $observer)
    {
        $orderObject = Mage::helper('tnw_salesforce')->getOrderObject();

        $_invoiceIds = $observer->getEvent()->getData('invoiceIds');
        $_message    = $observer->getEvent()->getMessage();
        $_type       = $observer->getEvent()->getType();
        $_isQueue    = $observer->getEvent()->getData('isQueue');

        $_queueIds = ($_isQueue) ? $observer->getEvent()->getData('queueIds') : array();

        if (count($_invoiceIds) == 1 && $_type == 'bulk') {
            $_type = 'salesforce';
        }

        $_objectType = strcasecmp(TNW_Salesforce_Helper_Config_Sales::SYNC_TYPE_ORDER, $orderObject) == 0
            ? 'order' : 'opportunity';

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Pushing Invoice ... ');
        $this->_processOrderPush($_invoiceIds, $_message, 'tnw_salesforce/' . $_type . '_' . $_objectType . '_invoice', $_queueIds);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @deprecated
     */
    //TODO: delete this method
    public function pushCreditMemo(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales_creditmemo')->syncCreditMemoForOrder()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Credit Memo synchronization disabled');
            return;
        }

        $_creditmemoIds = $observer->getEvent()->getData('creditmemoIds');
        $_message       = $observer->getEvent()->getMessage();
        $_type          = $observer->getEvent()->getType();
        $_isQueue       = $observer->getEvent()->getData('isQueue');

        $_queueIds = ($_isQueue) ? $observer->getEvent()->getData('queueIds') : array();

        if (count($_creditmemoIds) == 1 && $_type == 'bulk') {
            $_type = 'salesforce';
        }

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Pushing Credit Memo ... ');
        $this->_processOrderPush($_creditmemoIds, $_message, 'tnw_salesforce/' . $_type . '_order_creditmemo', $_queueIds);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @deprecated
     */
    //TODO: delete this method
    public function pushShipment(Varien_Event_Observer $observer)
    {
        $orderObject = Mage::helper('tnw_salesforce')->getOrderObject();

        $_shipmentIds = $observer->getEvent()->getData('shipmentIds');
        $_message     = $observer->getEvent()->getMessage();
        $_type        = $observer->getEvent()->getType();
        $_isQueue     = $observer->getEvent()->getData('isQueue');

        $_queueIds = ($_isQueue) ? $observer->getEvent()->getData('queueIds') : array();

        if (count($_shipmentIds) == 1 && $_type == 'bulk') {
            $_type = 'salesforce';
        }

        $_objectType = strcasecmp(TNW_Salesforce_Helper_Config_Sales::SYNC_TYPE_ORDER, $orderObject) == 0
            ? 'order' : 'opportunity';

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('Pushing Shipment ... ');
        $this->_processOrderPush($_shipmentIds, $_message, 'tnw_salesforce/' . $_type . '_' . $_objectType . '_shipment', $_queueIds);
    }

    /**
     * @param $_orderIds
     * @param $_message
     * @param $_model
     * @param $_queueIds
     * @deprecated
     */
    //TODO: delete this method
    protected function _processOrderPush($_orderIds, $_message, $_model, $_queueIds)
    {
        /**
         * @var $manualSync TNW_Salesforce_Helper_Salesforce_Abandoned_Opportunity|TNW_Salesforce_Helper_Salesforce_Opportunity|TNW_Salesforce_Helper_Salesforce_Order
         */
        $manualSync = Mage::helper($_model);
        if ($manualSync->reset()) {
            $checkAdd = $manualSync->massAdd($_orderIds);

            // Delete Skipped Entity
            $skipped = $manualSync->getSkippedEntity();
            if (!empty($skipped)) {
                $objectId = array();
                foreach ($skipped as $entity_id) {
                    $objectId[] = @$_queueIds[array_search($entity_id, $_orderIds)];
                }

                Mage::getModel('tnw_salesforce/localstorage')
                    ->deleteObject($objectId, true);
            }

            if ($checkAdd) {
                if ($_message === NULL && Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
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
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveSuccess($_message);
                    }
                }
            }
        } else {
            Mage::getModel('tnw_salesforce/localstorage')->updateObjectStatusById($_queueIds, 'new');

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Salesforce connection could not be established!");
            if ($_message === NULL) {
                Mage::throwException('Salesforce connection failed');
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function orderForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()){
            return; //Disabled
        }

        $entityIds = $observer->getData('entityIds');
        if (!is_array($entityIds)) {
            throw new InvalidArgumentException('entityIds argument not array');
        }

        //COSTbIL: check that order has been already exported
        foreach ($entityIds as $key => $orderId) {
            if (!in_array($orderId, $this->exportedOrders)) {
                $this->exportedOrders[] = $orderId;
                continue;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Skipping export order ' . $orderId . '. Already exported.');
            unset($entityIds[$key]);
        }

        if (empty($entityIds)) {
            return;
        }

        $observer->setData('entityIds', $entityIds);
        $observer->setData('entityPathPostfix', 'order');
        $observer->setData('successMessage', 'Total of %d order(s) were synchronized as Order');

        $this->entityForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function opportunityForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales')->integrationOpportunityAllowed()){
            return; //Disabled
        }

        $entityIds = $observer->getData('entityIds');
        if (!is_array($entityIds)) {
            throw new InvalidArgumentException('entityIds argument not array');
        }

        //COSTbIL: check that order has been already exported
        foreach ($entityIds as $key => $orderId) {
            if (!in_array($orderId, $this->exportedOpportunity['opportunity'])) {
                $this->exportedOpportunity['opportunity'][] = $orderId;
                continue;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Skipping export opportunity ' . $orderId . '. Already exported.');
            unset($entityIds[$key]);
        }

        if (empty($entityIds)) {
            return;
        }

        $observer->setData('entityIds', $entityIds);
        $observer->setData('entityPathPostfix', 'opportunity');
        $observer->setData('successMessage', 'Total of %d order(s) were synchronized as Opportunity');

        $this->entityForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function abandonedForWebsite($observer)
    {
        $entityIds = $observer->getData('entityIds');
        if (!is_array($entityIds)) {
            throw new InvalidArgumentException('entityIds argument not array');
        }

        //COSTbIL: check that order has been already exported
        foreach ($entityIds as $key => $orderId) {
            if (!in_array($orderId, $this->exportedOpportunity['abandoned'])) {
                $this->exportedOpportunity['abandoned'][] = $orderId;
                continue;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Skipping export opportunity ' . $orderId . '. Already exported.');
            unset($entityIds[$key]);
        }

        if (empty($entityIds)) {
            return;
        }

        $observer->setData('entityIds', $entityIds);
        $observer->setData('entityPathPostfix', 'abandoned_opportunity');
        $observer->setData('successMessage', 'Total of %d abandoned(s) were synchronized');

        $this->entityForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function orderInvoiceForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoicesForOrder()){
            return; //Disabled
        }

        $observer->setData('entityPathPostfix', 'order_invoice');
        $observer->setData('successMessage', 'Total of %d invoice(s) were synchronized');

        $this->entityForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function opportunityInvoiceForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales_invoice')->syncInvoicesForOpportunity()){
            return; //Disabled
        }

        $observer->setData('entityPathPostfix', 'opportunity_invoice');
        $observer->setData('successMessage', 'Total of %d invoice(s) were synchronized');

        $this->entityForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function orderShipmentForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipmentsForOrder()){
            return; //Disabled
        }

        $observer->setData('entityPathPostfix', 'order_shipment');
        $observer->setData('successMessage', 'Total of %d shipment(s) were synchronized');

        $this->entityForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function opportunityShipmentForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales_shipment')->syncShipmentsForOpportunity()){
            return; //Disabled
        }

        $observer->setData('entityPathPostfix', 'opportunity_shipment');
        $observer->setData('successMessage', 'Total of %d shipment(s) were synchronized');

        $this->entityForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function orderCreditmemoForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales_creditmemo')->syncCreditMemoForOrder()){
            return; //Disabled
        }

        $observer->setData('entityPathPostfix', 'order_creditmemo');
        $observer->setData('successMessage', 'Total of %d creditmemo(s) were synchronized');

        $this->entityForWebsite($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws InvalidArgumentException
     */
    public function entityForWebsite($observer)
    {
        $entityIds = $observer->getData('entityIds');
        if (!is_array($entityIds)) {
            throw new InvalidArgumentException('entityIds argument not array');
        }

        $entityPathPostfix = $observer->getData('entityPathPostfix');
        if (!is_string($entityPathPostfix)) {
            throw new InvalidArgumentException('entityIds argument not string');
        }

        switch ($observer->getData('syncType')) {
            case 'realtime':
                $syncType = 'salesforce';
                break;

            case 'bulk':
            default:
                $syncType = 'bulk';
                break;
        }

        /** @var TNW_Salesforce_Helper_Salesforce_Abstract_Sales $manualSync */
        $manualSync = Mage::helper(sprintf('tnw_salesforce/%s_%s', $syncType, $entityPathPostfix));

        $isCron = (bool)$observer->getData('isCron');
        $manualSync->setIsCron($isCron);

        // Add stack
        $syncObjectStack = $observer->getData('syncObjectStack');
        if ($syncObjectStack instanceof SplStack) {
            $syncObjectStack->push($manualSync);
        }

        if ($manualSync->reset() && $manualSync->massAdd($entityIds, $isCron) && $manualSync->process('full') && $successCount = $manualSync->countSuccessEntityUpsert()) {
            $successMessage = $observer->getData('successMessage');
            if (substr_count($successMessage, '%d') === 1) {
                $successMessage = sprintf($observer->getData('successMessage'), $successCount);
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveSuccess($successMessage);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     * @deprecated
     * @throws Exception
     */
    public function updateOpportunity(Varien_Event_Observer $observer)
    {
        $_order = $observer->getEvent()->getData('order');
        if (!$_order instanceof Mage_Sales_Model_Order) {
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace('Updating Opportunity Status ... ');

        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($_order->getStore()->getWebsite(), function () use($_order) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: API Integration is disabled');

                return;
            }

            if (!$helper->isEnabledOrderSync()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: Order Integration is disabled');

                return;
            }

            if (Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');

                return;
            }

            if (!$helper->canPush()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: Salesforce connection could not be established, SKIPPING sync');

                return;
            }

            if (!$helper->isRealTimeType()) {
                $success = Mage::getModel('tnw_salesforce/localstorage')
                    ->addObject(array($_order->getId()), 'Order', 'order');

                if (!$success) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError('Could not add to the queue!');
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveSuccess($helper->__('Records are pending addition into the queue!'));
                }

                return;
            }

            Mage::helper('tnw_salesforce/salesforce_opportunity')->updateStatus($_order);
        });
    }

    /**
     * @param Varien_Event_Observer $observer
     * @deprecated
     * @throws Exception
     */
    public function updateOrder(Varien_Event_Observer $observer)
    {
        $_order = $observer->getEvent()->getData('order');
        if (!$_order instanceof Mage_Sales_Model_Order) {
            return;
        }

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace('Updating Order Status ... ');

        Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($_order->getStore()->getWebsite(), function () use($_order) {
            /** @var TNW_Salesforce_Helper_Data $helper */
            $helper = Mage::helper('tnw_salesforce');

            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: API Integration is disabled');

                return;
            }

            if (!$helper->isEnabledOrderSync()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('SKIPPING: Order Integration is disabled');

                return;
            }

            if (Mage::getSingleton('core/session')->getFromSalesForce()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace('INFO: Updating from Salesforce, skip synchronization to Salesforce.');

                return;
            }

            if (!$helper->canPush()) {
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError('ERROR: Salesforce connection could not be established, SKIPPING sync');

                return;
            }

            if (!$helper->isRealTimeType()) {
                $success = Mage::getModel('tnw_salesforce/localstorage')
                    ->addObject(array($_order->getId()), 'Order', 'order');

                if (!$success) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError('Could not add to the queue!');
                } else {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveSuccess($helper->__('Records are pending addition into the queue!'));
                }

                return;
            }

            Mage::helper('tnw_salesforce/salesforce_order')->updateStatus($_order);
        });
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws Exception
     */
    public function opportunityStatusForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales')->integrationOpportunityAllowed()){
            return; //Disabled
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof Mage_Sales_Model_Order) {
            return;
        }

        Mage::helper('tnw_salesforce/salesforce_opportunity')->updateStatus($order);
    }

    /**
     * @param Varien_Event_Observer $observer
     * @throws Exception
     */
    public function orderStatusForWebsite($observer)
    {
        if (!Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()){
            return; //Disabled
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof Mage_Sales_Model_Order) {
            return;
        }

        Mage::helper('tnw_salesforce/salesforce_order')->updateStatus($order);
    }

    public function updateOrderStatusForm($observer)
    {
        /** @var Varien_Data_Form $_form */
        $_form = $observer->getForm();

        if (Mage::helper('tnw_salesforce')->isWorking()) {
            $fieldset = $_form->addFieldset('sf_fieldset', array(
                'legend' => Mage::helper('tnw_salesforce')->__('Salesforce Status')
            ), 'base_fieldset');

            $_sfData = Mage::helper('tnw_salesforce/salesforce_data');
            if (Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()) {
                $sfFields = array(array(
                    'value' => '',
                    'label' => 'Choose Salesforce Status ...'
                ));

                $states = $_sfData->getPicklistValues('Order', 'Status');
                if (!is_array($states)) {
                    $states = array();
                }
                foreach ($states as $field) {
                    $sfFields[] = array(
                        'value' => $field->label,
                        'label' => $field->label
                    );
                }

                $fieldset->addField('sf_order_status', 'select',
                    array(
                        'name' => 'sf_order_status',
                        'label' => Mage::helper('tnw_salesforce')->__('Order Status'),
                        'class' => 'required-entry',
                        'required' => false,
                        'values' => $sfFields
                    )
                );
            }

            if (Mage::helper('tnw_salesforce/config_sales')->integrationOpportunityAllowed()) {
                $sfFields = array(array(
                    'value' => '',
                    'label' => 'Choose Salesforce Status ...'
                ));

                $states = $_sfData->getStatus('Opportunity');
                if (!is_array($states)) {
                    $states = array();
                }
                foreach ($states as $field) {
                    $sfFields[] = array(
                        'value' => $field->MasterLabel,
                        'label' => $field->MasterLabel
                    );
                }

                $fieldset->addField('sf_opportunity_status_code', 'select',
                    array(
                        'name' => 'sf_opportunity_status_code',
                        'label' => Mage::helper('tnw_salesforce')->__('Opportunity StageName'),
                        'class' => 'required-entry',
                        'required' => false,
                        'values' => $sfFields
                    )
                );
            }

            Mage::dispatchEvent('tnw_salesforce_order_status_new_form_update', array('form' => $_form, 'fieldset' => $fieldset));
        }
    }

    public function saveSfStatus($observer)
    {
        $statusCode = $observer->getStatus();
        $_request = $observer->getRequest();

        $orderStatusMapping = Mage::getModel('tnw_salesforce/order_status')
            ->load($statusCode, 'status');

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
        if (!Mage::helper('tnw_salesforce/config_sales_abandoned')->isEnabled()) {
            return false;
        }

        /** @var $quote Mage_Sales_Model_Quote */
        $quote = $observer->getEvent()->getQuote();
        if (
            !$quote
            || !$quote->getData('customer_id')
            || !$quote->getData('customer_email')
        ) {
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

        $abandonedOpportunities = $closeDateOpportunities = array();

        foreach ($orders as $key => $order) {
            if (property_exists($order, $opportunityField)) {
                if (!empty($order->tnw_mage_basic__Magento_ID__c)) {
                    $closeDateOpportunities[$order->tnw_mage_basic__Magento_ID__c] = $order->$opportunityField;
                }

                $abandonedOpportunities[] = $order->$opportunityField;
            }
        }

        if (!empty($abandonedOpportunities)) {
            /**
             * @var $collection TNW_Salesforce_Model_Api_Entity_Resource_Opportunity_Collection
             */
            $collection = Mage::getModel('tnw_salesforce/api_entity_opportunity')->getCollection();
            $collection->addFieldToFilter('Id', array('in' => $abandonedOpportunities));

            /** @var Mage_Sales_Model_Resource_Order_Invoice $resource */
            $resource = Mage::getResourceModel('sales/order_invoice');
            $connection = $resource->getReadConnection();
            $select = $connection->select()
                ->from(array('invoice' => $resource->getMainTable()), array('created_at'))
                ->joinInner(array('order'=>$resource->getTable('sales/order')), 'order.entity_id = invoice.order_id', array())
                ->order('invoice.created_at DESC')
                ->where('order.increment_id = :order')
            ;

            foreach ($collection as $opportunity) {
                $orderIncrementId = array_search($opportunity->getData('Id'), $closeDateOpportunities);
                if (false === $orderIncrementId) {
                    continue;
                }

                $createdAt = $connection->fetchOne($select, array('order' => $orderIncrementId));
                $currentTimezone = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
                $dateTime = new DateTime($createdAt, new DateTimeZone('UTC'));
                $dateTime->setTimezone(new DateTimeZone($currentTimezone));

                $opportunity->setData('CloseDate', $dateTime->format('c'));
            }

            $collection->setDataToAll('StageName', Mage::helper('tnw_salesforce/config_sales')->getOpportunityToOrderStatus());
            $collection->save();
        }

    }

    /**
     * @param $observer Varien_Event_Observer
     */
    public function cacheEntityClear($observer)
    {
        $eventTypeName = $observer->getEvent()->getName();
        $cacheSection = array(
            'salesforce',
            'salesforce_customer',
            'salesforce_product',
            'salesforce_order',
            'salesforce_contactus',
            'salesforce_promotion',
            'salesforce_invoice',
            'salesforce_shipment',
            'salesforce_creditmemo',
        );

        if ($eventTypeName == 'admin_system_config_section_save_after' && in_array($observer->getData('section'), $cacheSection)) {
            Mage::getSingleton('tnw_salesforce/session')->clear();
            Mage::app()->getCacheInstance()->cleanType('tnw_salesforce');
            return;
        }

        if ($eventTypeName == 'adminhtml_cache_refresh_type' && strcasecmp($observer->getData('type'), 'tnw_salesforce') != 0) {
            return;
        }

        Mage::app()->getCacheInstance()->cleanType('tnw_salesforce');
        Mage::getSingleton('tnw_salesforce/session')->clear();
        Mage::getResourceModel('tnw_salesforce/entity_cache')->clearAll();
    }
}