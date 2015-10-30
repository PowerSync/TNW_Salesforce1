<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Adminhtml_Tool_LogController
 */
class TNW_Salesforce_Adminhtml_Tool_LogController extends Mage_Adminhtml_Controller_Action
{

    /**
     * show grid page
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_tool_log'));
        $this->renderLayout();
    }

    /**
     * save as Csv
     */
    public function exportCsvAction()
    {
        $fileName = 'DB Log_export.csv';
        $content = $this->getLayout()->createBlock('tnw_salesforce/adminhtml_tool_log_grid')->getCsv();
        $this->_prepareDownloadResponse($fileName, $content);
    }

    /**
     * Save as Excel
     */
    public function exportExcelAction()
    {
        $fileName = 'DB Log_export.xml';
        $content = $this->getLayout()->createBlock('tnw_salesforce/adminhtml_tool_log_grid')->getExcel();
        $this->_prepareDownloadResponse($fileName, $content);
    }

    /**
     * Delete log records
     */
    public function massDeleteAction()
    {
        $ids = $this->getRequest()->getParam('ids');
        if (!is_array($ids)) {
            $this->_getSession()->addError($this->__('Please select DB Log(s).'));
        } else {
            try {
                foreach ($ids as $id) {
                    $model = Mage::getSingleton('tnw_salesforce/tool_log')->load($id);
                    $model->delete();
                }

                $this->_getSession()->addSuccess(
                    $this->__('Total of %d record(s) have been deleted.', count($ids))
                );
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError(
                    Mage::helper('tnw_salesforce')->__('An error occurred while mass deleting items. Please review log and try again.')
                );
                Mage::logException($e);
                return;
            }
        }
        $this->_redirect('*/*/index');
    }

    /**
     * delete record
     */
    public function deleteAction()
    {
        if ($id = $this->getRequest()->getParam('id')) {
            try {
                // init model and delete
                $model = Mage::getModel('tnw_salesforce/tool_log');
                $model->load($id);
                if (!$model->getId()) {
                    Mage::throwException(Mage::helper('tnw_salesforce')->__('Unable to find a DB Log to delete.'));
                }
                $model->delete();
                // display success message
                $this->_getSession()->addSuccess(
                    Mage::helper('tnw_salesforce')->__('The DB Log has been deleted.')
                );
                // go to grid
                $this->_redirect('*/*/index');
                return;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError(
                    Mage::helper('tnw_salesforce')->__('An error occurred while deleting DB Log data. Please review log and try again.')
                );
                Mage::logException($e);
            }
            // redirect to edit form
            $this->_redirect('*/*/view', array('id' => $id));
            return;
        }

        $this->_getSession()->addError(
            Mage::helper('tnw_salesforce')->__('Unable to find a DB Log to delete.')
        );

        $this->_redirect('*/*/index');
    }
}
