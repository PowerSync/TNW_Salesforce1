<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Adminhtml_Salesforcemisc_Log_FileController
 */
class TNW_Salesforce_Adminhtml_Salesforcemisc_Log_FileController extends Mage_Adminhtml_Controller_Action
{

    /**
     * load log file
     *
     * @return TNW_Salesforce_Model_Salesforcemisc_Log_File
     * @throws Exception
     */
    protected function _initLogFile()
    {
        try {
            $filename = $this->getRequest()->getParam('filename', false);

            $logFile = Mage::getModel('tnw_salesforce/salesforcemisc_log_file')->load($filename);

            if (!$logFile->exists()) {
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
     * @return $this
     */
    protected function _prepareAction()
    {
        $this->_title($this->__('Salesforce'))->_title($this->__('Logs'));

        $this->_setActiveMenu('tnw_salesforce');
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Salesforcecore'), Mage::helper('adminhtml')->__('Salesforcecore'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Logs'), Mage::helper('adminhtml')->__('Log'));

        return $this;
    }

    /**
     * Log list action
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->_prepareAction();

        $this->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_salesforcemisc_log_file', 'log_file'));

        $this->renderLayout();
    }

    /**
     * Log view action
     */
    public function viewAction()
    {
        $this->loadLayout();
        $this->_prepareAction();

        $this->_initLogFile();

        $this->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_salesforcemisc_log_file_view', 'log_file_view'));

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

        /* @var $log TNW_Salesforce_Model_Salesforcemisc_Log_File */
        $log = Mage::getModel('tnw_salesforce/salesforcemisc_log_file')->loadByName($fileName);

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

        /** @var $logModel TNW_Salesforce_Model_Salesforcemisc_Log_File */
        $logModel = Mage::getModel('tnw_salesforce/salesforcemisc_log_file');
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
