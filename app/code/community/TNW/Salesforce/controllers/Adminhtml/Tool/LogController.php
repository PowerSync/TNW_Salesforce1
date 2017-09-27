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
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('tnw_salesforce/tools/sync_log');
    }

    /**
     * @return $this
     */
    protected function _prepareAction()
    {
        $this->_title($this->__('Salesforce'))->_title($this->__('Tools'))->_title($this->__('Transaction Logs'));


        $this->_setActiveMenu('tnw_salesforce');
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Salesforce'), Mage::helper('adminhtml')->__('Salesforce'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Logs'), Mage::helper('adminhtml')->__('Log'));

        return $this;
    }

    /**
     * show grid page
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->_prepareAction();
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
        $content = $this->getLayout()->createBlock('tnw_salesforce/adminhtml_tool_log_grid')->getExcelFile();
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

    public function generateDumpAction()
    {
        $phar = new PharData(sys_get_temp_dir(). DS . 'dump.tar');

        /** @var Mage_Core_Model_Website $website */
        foreach (Mage::app()->getWebsites(true) as $website) {
            Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () use($website, $phar) {
                $wsdlFile = TNW_Salesforce_Model_Connection::createConnection()->getWsdl();
                if (is_file($wsdlFile)) {
                    $phar->addFile($wsdlFile, "{$website->getCode()}/soapclient.wsdl");
                }

                $csv = fopen('php://memory', 'rw+');
                $config = Mage::getConfig()->loadModulesConfiguration('system.xml')
                    ->applyExtends();

                /** @var Mage_Core_Model_Config_Element $node */
                foreach ($config->getXpath('//sections/*[@module="tnw_salesforce"]/groups/*/fields/*') as $node) {
                    if ($node->config_path) {
                        $path = (string)$node->config_path;
                    } else {
                        $section = $node->getParent()->getParent()->getParent()->getParent()->getName();
                        $group = $node->getParent()->getParent()->getName();
                        $path = "$section/$group/{$node->getName()}";
                    }

                    fputcsv($csv, array($path, Mage::getStoreConfig($path)));
                }

                rewind($csv);
                $content = stream_get_contents($csv);
                fclose($csv);

                $phar->addFromString("{$website->getCode()}/config.csv", $content);
            });
        }

        $csv = fopen('php://memory', 'rw+');

        $collection = Mage::getResourceModel('tnw_salesforce/mapping_collection');
        $collection->setOrder('sf_object');
        fputcsv($csv, array_keys($collection->getFirstItem()->getData()));
        /** @var TNW_Salesforce_Model_Mapping $mapping */
        foreach ($collection as $mapping) {
            fputcsv($csv, $mapping->getData());
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        $phar->addFromString('admin/mapping.csv', $content);

        if (extension_loaded('zlib')) {
            unlink($phar->getPath() . '.gz');
            return $this->_prepareDownloadResponse('dump'.Mage::getSingleton('core/date')->date('Y-m-d_H-i-s').'.tar.gz', array(
                'value'=>$phar->compress(Phar::GZ)->getPath(),
                'type'=>'filename'
            ), 'application/x-compressed-tar');
        }

        return $this->_prepareDownloadResponse('dump'.Mage::getSingleton('core/date')->date('Y-m-d_H-i-s').'.tar', array(
            'value'=>$phar->getPath(),
            'type'=>'filename'
        ), 'application/x-tar');
    }
}
