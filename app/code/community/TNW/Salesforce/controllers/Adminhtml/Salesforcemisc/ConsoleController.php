<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Adminhtml_LogController
 */
class TNW_Salesforce_Adminhtml_Salesforcemisc_ConsoleController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Log list action
     */
    public function indexAction()
    {
        $this->_title($this->__('Salesforce'))->_title($this->__('Console'));

        $this->loadLayout();
        $this->_setActiveMenu('tnw_salesforce');
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Salesforcecore'), Mage::helper('adminhtml')->__('Salesforcecore'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Console'), Mage::helper('adminhtml')->__('Log'));

        $sql = Mage::getSingleton('admin/session')->getData('sql');
        Mage::register('sql', $sql);

        $sql_result = Mage::getSingleton('admin/session')->getData('sql_result');
        Mage::register('sql_result', $sql_result);

        $this->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_salesforcemisc_console', 'console'));

        $this->renderLayout();
    }

    /**
     *
     */
    public function queryAction()
    {
        try {
            $sql = Mage::app()->getRequest()->getPost('sql');

            Mage::getSingleton('admin/session')->setData('sql', $sql);

            /**
             * Execute the query and store the results in $results
             */
            $result = Mage::getSingleton('tnw_salesforce/api_entity_adapter')->fetchAll($sql);

            $collection = new Varien_Data_Collection();
            foreach ($result as $record) {

                $item = new Varien_Object();

                foreach ($record as $field => $value) {
                    $item->setData($field, $value);
                }
                $collection->addItem($item);
            }

            Mage::getSingleton('admin/session')->setData('sql_result', $collection);

        } catch (Exception $e) {
            Mage::getSingleton('admin/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/');

    }

}
