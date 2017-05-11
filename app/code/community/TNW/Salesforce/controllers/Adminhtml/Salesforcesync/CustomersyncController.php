<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_CustomersyncController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = array('grid', 'index');

    protected function _initLayout()
    {
        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Customer Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Customer Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Customer'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_synchronize_customer'));

        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     * Sync Action
     * @throws Exception
     */
    public function syncAction()
    {
        $entityId = $this->getRequest()->getParam('customer_id');
        Mage::getSingleton('tnw_salesforce/customer_observer')->syncCustomer(array($entityId), true);

        $this->_redirectReferer($this->getUrl('*/*/index', array('_current' => true)));
    }

    public function massSyncAction()
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $itemIds = $this->getRequest()->getParam('customers');
        if (!is_array($itemIds)) {
            $this->_getSession()->addError($helper->__('Please select customer(s)'));
        } elseif (!$helper->isProfessionalEdition()) {
            $this->_getSession()->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {
            Mage::getSingleton('tnw_salesforce/customer_observer')->syncCustomer($itemIds, true);
        }

        $this->_redirect('*/*/index');
    }
}
