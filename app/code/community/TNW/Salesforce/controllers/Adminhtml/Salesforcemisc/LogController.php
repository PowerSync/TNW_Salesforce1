<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Adminhtml_LogController
 */
class TNW_Salesforce_Adminhtml_Salesforcemisc_LogController extends Mage_Adminhtml_Controller_Action
{

    /**
     * load log file
     *
     * @return TNW_Salesforce_Model_Log
     * @throws Exception
     */
    protected function _initLogFile()
    {
        try {
            $filename = (int)$this->getRequest()->getParam('filename', false);

            $logFile = Mage::getModel('tnw_salesforce/log')->load($filename);

            if (!$logFile->exists($filename)) {
                throw new Exception($this->__('File is not available'));
            }

            $logFile->read();

            Mage::register('tnw_salesforce_log_file', $logFile);

        } catch (Exception $e) {
            throw new Exception($this->__('Cannot open this file'));
        }

        return $logFile;
    }

    /**
     * Log list action
     */
    public function indexAction()
    {
        $this->_title($this->__('Salesforce'))->_title($this->__('Logs'));

        $this->loadLayout();
        $this->_setActiveMenu('tnw_salesforce');
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Salesforcecore'), Mage::helper('adminhtml')->__('Salesforcecore'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Logs'), Mage::helper('adminhtml')->__('Log'));

        $this->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_salesforcemisc_log', 'log'));

        $this->renderLayout();
    }

    /**
     * Log view action
     */
    public function viewAction()
    {
        $this->_title($this->__('Salesforce'))->_title($this->__('Logs'));

        $this->loadLayout();
        $this->_setActiveMenu('tnw_salesforce');
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Salesforce'), Mage::helper('adminhtml')->__('Salesforce'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Logs'), Mage::helper('adminhtml')->__('Log'));

        $this->_initLogFile();

        $this->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_salesforcemisc_log_view', 'log_view'));

        $this->renderLayout();
    }

    /**
     * Download log action
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    public function downloadAction()
    {
        $fileName = $this->getRequest()->getParam('filename');

        /* @var $log TNW_Salesforce_Model_Log */
        $log = Mage::getModel('tnw_salesforce/log')->loadByName($fileName);

        if (!$log->exists()) {
            return $this->_redirect('*/*');
        }

        $this->_prepareDownloadResponse($fileName, null, 'application/octet-stream', $log->getSize());

        $this->getResponse()->sendHeaders();

        $log->output();
        exit();
    }

    /**
     * Delete logs mass action
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    public function massDeleteAction()
    {
        $logIds = $this->getRequest()->getParam('ids', array());

        if (!is_array($logIds) || !count($logIds)) {
            return $this->_redirect('*/*/index');
        }

        /** @var $logModel TNW_Salesforce_Model_Log */
        $logModel = Mage::getModel('tnw_salesforce/log');
        $resultData = new Varien_Object();
        $resultData->setIsSuccess(false);
        $resultData->setDeleteResult(array());

        $deleteFailMessage = Mage::helper('tnw_salesforce')->__('Failed to delete one or several logs.');

        try {
            $allLogsDeleted = true;

            foreach ($logIds as $name) {

                $logModel
                    ->loadByName($name)
                    ->deleteFile();

                if ($logModel->exists()) {
                    $allLogsDeleted = false;
                    $result = Mage::helper('adminhtml')->__('failed');
                } else {
                    $result = Mage::helper('adminhtml')->__('successful');
                }

                $resultData->setDeleteResult(
                    array_merge($resultData->getDeleteResult(), array($logModel->getFileName() . ' ' . $result))
                );
            }

            $resultData->setIsSuccess(true);
            if ($allLogsDeleted) {
                $this->_getSession()->addSuccess(
                    Mage::helper('tnw_salesforce')->__('The selected log(s) has been deleted.')
                );
            } else {
                throw new Exception($deleteFailMessage);
            }
        } catch (Exception $e) {
            $resultData->setIsSuccess(false);
            $this->_getSession()->addError($deleteFailMessage);
        }

        return $this->_redirect('*/*/index');
    }

    /**
     * Retrive adminhtml session model
     *
     * @return Mage_Adminhtml_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }
}
