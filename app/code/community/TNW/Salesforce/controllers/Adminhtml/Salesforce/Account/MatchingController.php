<?php

class TNW_Salesforce_Adminhtml_Salesforce_Account_MatchingController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return $this
     */
    protected function _initLayout()
    {
        /** @var  TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        if (!$helper->isWorking()) {
            Mage::getSingleton('adminhtml/session')
                ->addError("API Integration is disabled.");

            $this->getResponse()
                ->setRedirect($this->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')))
                ->sendResponse();

            return $this;
        }

        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb($helper->__('Account Field Mapping'), $helper->__('Account Field Mapping'));

        return $this;
    }

    /**
     * Create or load Account Matching model
     * @param int $loadId
     * @return TNW_Salesforce_Model_Account_Matching
     */
    protected static function _createEntityModel($loadId = null)
    {
        $model = Mage::getModel('tnw_salesforce/account_matching');
        if (!is_null($loadId)) {
            $loadId = max(intval($loadId), 0);

            if (!empty($loadId)) {
                $model->load($loadId);
            }
        }

        return $model;
    }

    /**
     * Index Action
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Salesforce API'))
            ->_title($this->__('Account Matching'));

        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_account_matching'));

        Mage::helper('tnw_salesforce')
            ->addAdminhtmlVersion('TNW_Salesforce');

        $this->renderLayout();
    }

    /**
     * Matching import
     */
    public function matchingImportAction()
    {
        /** @var Mage_Adminhtml_Model_Session $session */
        $session    = Mage::getSingleton('adminhtml/session');

        try {

            /** Upload import File */
            if(isset($_FILES['fileImport']['name']) && !empty($_FILES['fileImport']['name']))
            {
                $uploader = new Varien_File_Uploader('fileImport');

                $uploader->setAllowedExtensions(array('csv'));
                $uploader->setAllowRenameFiles(false);
                $uploader->setFilesDispersion(false);

                $_filePath = tempnam(sys_get_temp_dir(), 'Mat');

                // Upload the image
                $uploader->save(dirname($_filePath), basename($_filePath));

                /** @var TNW_Salesforce_Model_Account_Matching_Import $import */
                $import = Mage::getModel('tnw_salesforce/account_matching_import');
                try {
                    $import->importByFilename($_filePath);

                    $status = $import->getStatus();
                    if ($status['success']['count'] > 0) {
                        //todo
                    }

                    if ($status['error']['count'] > 0) {
                        //todo
                    }
                }
                catch(Exception $e) {
                    //todo
                }
            }
        }
        catch(Exception $e) {
            $session->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    /**
     * New Action (forward to edit action)
     */
    public function newAction()
    {
        $this->_forward('edit');
    }

    /**
     * Edit Action
     */
    public function editAction()
    {
        $matchingId = $this->getRequest()->getParam('matching_id');
        $model = self::_createEntityModel($matchingId);

        if (is_null($model->getId()) && !is_null($matchingId)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('tnw_salesforce')->__('Item does not exist'));
            $this->_redirect('*/*/');

            return;
        }

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('salesforce_account_matching_data', $model);

        $this->_initLayout()
            ->getLayout();

        $this->_addContent(
            $this->getLayout()->createBlock('tnw_salesforce/adminhtml_account_matching_edit'));
        Mage::helper('tnw_salesforce')
            ->addAdminhtmlVersion('TNW_Salesforce');

        $this->renderLayout();
    }

    /**
     * Save Action
     */
    public function saveAction()
    {
        $data       = $this->getRequest()->getPost();
        /** @var Mage_Adminhtml_Model_Session $session */
        $session    = Mage::getSingleton('adminhtml/session');
        $matchingId = $this->getRequest()->getParam('matching_id');

        if (empty($data)) {
            $session->addError(Mage::helper('tnw_salesforce')->__('Unable to find matching to save'));
            $this->_redirect('*/*/');
            return;
        }

        /** @var TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection $collection */
        $collection = Mage::getModel('tnw_salesforce_api_entity/account')->getCollection();
        try {
            $data['account_name'] = '';
            $toOptionHash = $collection->toOptionHashCustom();
            foreach ($toOptionHash as $id=>$name) {
                if ($data['account_id'] == Mage::helper('tnw_salesforce/data')->prepareId($id)) {
                    $data['account_name'] = $name;
                    break;
                }
            }

        } catch(Exception $e) {}

        /** @var TNW_Salesforce_Model_Account_Matching $model */
        $model = self::_createEntityModel()
            ->setData($data)
            ->setId($matchingId);

        try {
            $model->save();

            $session->addSuccess(
                Mage::helper('tnw_salesforce')->__('Mapping was successfully saved'));
            $session->setFormData(false);

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', array('matching_id' => $matchingId));
                return;
            }

            $this->_redirect('*/*/');
            return;
        }
        catch (Zend_Db_Statement_Exception $e){
            if ($e->getCode() == 23000) {
                $session->addError(Mage::helper('tnw_salesforce')->__('Domain (%s) already registered', $data['email_domain']));
            }
            else {
                $session->addError($e->getMessage());
            }

            $session->setFormData($data);

            $this->_redirect('*/*/edit', array('matching_id' => $matchingId));
            return;
        }
        catch (Exception $e) {
            $session->addError($e->getMessage());
            $session->setFormData($data);

            $this->_redirect('*/*/edit', array('matching_id' => $matchingId));
            return;
        }
    }

    /**
     * Delete Action
     */
    public function deleteAction()
    {
        $matching_id = $this->getRequest()->getParam('matching_id');
        $matching_id = max($matching_id, 0);

        if (empty($matching_id)) {
            $this->_redirect('*/*/');
            return;
        }

        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        try {
            self::_createEntityModel()
                ->setId($matching_id)
                ->delete();

            $session->addSuccess(
                Mage::helper('tnw_salesforce')->__('Mapping was successfully deleted'));
            $this->_redirect('*/*/');
            return;
        }
        catch (Exception $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/edit', array('matching_id' => $matching_id));
            return;
        }
    }

    /**
     * Mass delete action
     */
    public function massDeleteAction()
    {
        $itemIds = $this->getRequest()->getParam('matching');
        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        if (!is_array($itemIds)) {
            $session->addError(Mage::helper('tnw_salesforce')->__('Please select record(s)'));
            $this->_redirect('*/*/index');
            return;
        }

        try {
            foreach ($itemIds as $itemId) {
                self::_createEntityModel($itemId)->delete();
            }

            $session->addSuccess(
                Mage::helper('adminhtml')->__('Total of %d record(s) were successfully deleted', count($itemIds)));
        }
        catch (Exception $e) {
            $session->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    /**
     * Export matching grid to CSV format
     */
    public function exportCsvAction()
    {
        $fileName   = 'matching.csv';
        $grid       = $this->getLayout()->createBlock('tnw_salesforce/adminhtml_account_matching_grid');
        $this->_prepareDownloadResponse($fileName, $grid->getCsvFile());
    }
}
