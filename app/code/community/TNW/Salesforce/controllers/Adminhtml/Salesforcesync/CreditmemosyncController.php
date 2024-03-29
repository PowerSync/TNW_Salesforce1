<?php

class TNW_Salesforce_Adminhtml_Salesforcesync_CreditmemosyncController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = array('grid', 'index');

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('tnw_salesforce/manual_sync/creditmemo_sync');
    }

    protected function _construct()
    {
        $this->setUsedModuleName('Mage_Sales');
    }

    protected function _initLayout()
    {
        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Invoice Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Invoice Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Salesforce API'))
            ->_title($this->__('Manual Sync'))
            ->_title($this->__('Credit Memo'));

        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_synchronize_creditmemo'));

        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');
        $this->renderLayout();
    }

    /**
     * Order grid
     */
    public function gridAction()
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     * Sync Action
     *
     */
    public function syncAction()
    {
        $creditMemoId = $this->getRequest()->getParam('creditmemo_id');
        Mage::getSingleton('tnw_salesforce/order_creditmemo_observer')->syncCreditMemo(array($creditMemoId), true);

        $this->_redirectReferer();
    }

    public function massSyncForceAction()
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $itemIds = $this->getRequest()->getParam('creditmemo_ids');
        if (!is_array($itemIds)) {
            $this->_getSession()->addError($helper->__('Please select credit memo(s)'));
        } else {
            Mage::getSingleton('tnw_salesforce/order_creditmemo_observer')->syncCreditMemo($itemIds, true);
        }

        $this->_redirect('*/*/index');
    }
}
