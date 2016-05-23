<?php

class TNW_Salesforce_Adminhtml_Salesforce_Creditmemo_StatusController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('system/salesforce/creditmemo_mapping/status_mapping');
    }

    /**
     * @return $this
     */
    protected function _initLayout()
    {
        $this->loadLayout()
            ->_setActiveMenu('system/salesforce')
            ->_addBreadcrumb($this->__('Credit Memo status Mapping'), $this->__('Credit Memo status Mapping'));

        return $this;
    }

    /**
     * Action
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Salesforce'))
            ->_title($this->__('Credit Memo status Mapping'));

        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_creditmemostatus'));

        $this->renderLayout();
    }

    /**
     * Action
     */
    public function newAction()
    {
        $this->_forward('edit');
    }

    public function editAction()
    {

    }

    /**
     * Save Action
     */
    public function saveAction()
    {

    }

    /**
     * Delete Action
     */
    public function deleteAction()
    {

    }
}