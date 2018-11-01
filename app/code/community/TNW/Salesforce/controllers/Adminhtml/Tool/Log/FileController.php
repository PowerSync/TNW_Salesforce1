<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Adminhtml_Tool_Log_FileController
 */
class TNW_Salesforce_Adminhtml_Tool_Log_FileController extends Mage_Adminhtml_Controller_Action
{

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('tnw_salesforce/tools/sync_log_file');
    }

    /**
     * load log file
     *
     * @return TNW_Salesforce_Model_Tool_Log_File
     * @throws Exception
     */
    protected function _initLogFile()
    {
        try {
            $filename = $this->getRequest()->getParam('filename', false);

            $logFile = $this->_loadPageToolLogFile($filename);
            Mage::register('tnw_salesforce_log_file', $logFile);
        }
        catch (Exception $e) {
            throw new Exception($this->__('Cannot open this file'));
        }

        return $this;
    }

    /**
     * @param $filename
     * @param int $page
     * @return TNW_Salesforce_Model_Tool_Log_File
     * @throws Exception
     */
    protected function _loadPageToolLogFile($filename, $page = 1)
    {
        /** @var TNW_Salesforce_Model_Tool_Log_File $logFile */
        $logFile = Mage::getModel('tnw_salesforce/tool_log_file')->load($filename);
        if (!$logFile->exists()) {
            throw new Exception($this->__('File is not available'));
        }

        $pageSize   = Mage::helper('tnw_salesforce/config_tool')->getLogFileReadLimit();
        $pageSize   = max((int)$pageSize, 0);
        $page       = max((int)$page, 1);

        return $logFile->read($page, $pageSize, SEEK_END);
    }

    /**
     * @return $this
     */
    protected function _prepareAction()
    {
        $this->_title($this->__('Salesforce'))
            ->_title($this->__('Tools'))
            ->_title($this->__('Download Log Files'));

        return $this->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Salesforce'), Mage::helper('adminhtml')->__('Salesforce'))
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Logs'), Mage::helper('adminhtml')->__('Log'));
    }

    /**
     * Log list action
     */
    public function indexAction()
    {
        $this->loadLayout()
            ->_prepareAction();

        $this->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_tool_log_file', 'log_file'))
            ->renderLayout();
    }

    /**
     * Log view action
     */
    public function viewAction()
    {
        $this->loadLayout()
            ->_prepareAction()
            ->_initLogFile();

        $this->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_tool_log_file_view', 'log_file_view'))
            ->renderLayout();
    }

    /**
     * Download log action
     */
    public function downloadAction()
    {
        $fileName = $this->getRequest()->getParam('filename');

        /* @var $log TNW_Salesforce_Model_Tool_Log_File */
        $log = Mage::getModel('tnw_salesforce/tool_log_file')->loadByName($fileName);
        if (!$log->exists()) {
            $this->_redirect('*/*');
            return;
        }

        $this->_prepareDownloadResponse($fileName, array(
            'type'  => 'filename',
            'value' => $log->getPath() . DS . $log->getFileName()
        ), 'application/octet-stream');
    }

    /**
     * Get file content
     * @throws Exception
     */
    public function fileContentAction()
    {
        $filename = $this->getRequest()->getParam('filename', false);
        $page     = $this->getRequest()->getParam('page', 1);

        $logFile = $this->_loadPageToolLogFile($filename, $page);

        $this->getResponse()
            ->setHeader('Content-type', 'text/plain; charset=UTF-8')
            ->setBody($logFile->getContent());
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

        /** @var $logModel TNW_Salesforce_Model_Tool_Log_File */
        $logModel = Mage::getModel('tnw_salesforce/tool_log_file');
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
