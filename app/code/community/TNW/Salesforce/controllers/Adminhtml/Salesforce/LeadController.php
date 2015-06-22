<?php

class TNW_Salesforce_Adminhtml_Salesforce_LeadController extends Mage_Adminhtml_Controller_Action
{
    protected function _initLayout()
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('adminhtml/session')->addError("API Integration is disabled.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')));
            Mage::app()->getResponse()->sendResponse();
        }

        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Lead Fields Mapping'), Mage::helper('tnw_salesforce')->__('Lead Field Mapping'));
        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Lead Field Mapping'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_lead'));
        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');
        $this->renderLayout();
    }

    /**
     * New Action (forward to edit action)
     *
     */
    public function newAction()
    {
        $this->_forward('edit');
    }

    /**
     * Edit Action
     *
     */
    public function editAction()
    {
        $id = $this->getRequest()->getParam('mapping_id');
        $model = Mage::getModel('tnw_salesforce/mapping')->load($id);
        if ($model->getId() || $id == 0) {
            $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
            if (!empty($data)) {
                $model->setData($data);
            }

            Mage::register('salesforce_lead_data', $model);

            $this->loadLayout();
            $this->_setActiveMenu('system/salesforce');

            $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);

            $this->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_lead_edit'));
            Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');
            $this->renderLayout();
        } else {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Item does not exist'));
            $this->_redirect('*/*/');
        }
    }

    /**
     * Save Action
     *
     */
    public function saveAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            $data['sf_object'] = "Lead";
            // Inject custom logic for custom fields
            if ($data['local_field'] == "Custom : field") {
                $locAattr = array(strstr($data['local_field'], ' : ', true));
                array_push($locAattr, $data['default_code']);
                $data['local_field'] = join(" : ", $locAattr);
                unset($data['default_code']);
            } else {
                $mageGroup = explode(" : ", $data['local_field']);
                if ($mageGroup[0] == "Customer") {
                    $attrId = Mage::getResourceModel('eav/entity_attribute')
                        ->getIdByCode("customer", $mageGroup[1]);
                    $attr = Mage::getModel('catalog/resource_eav_attribute')->load($attrId);
                    $data['attribute_id'] = $attr->getId();
                    $data['backend_type'] = $attr->getBackendType();
                } else if ($mageGroup[0] == "Shipping" || $mageGroup[0] == "Billing") {
                    $attrId = Mage::getResourceModel('eav/entity_attribute')
                        ->getIdByCode("customer_address", $mageGroup[1]);
                    $attr = Mage::getModel('catalog/resource_eav_attribute')->load($attrId);
                    $data['attribute_id'] = $attr->getId();
                    $data['backend_type'] = $attr->getBackendType();
                }
                unset($data['default_code']);
                $data['default_value'] = NULL;
            }

            // validate
            if (!$this->_validate($data)) {
                Mage::getSingleton('adminhtml/session')->addError("Attribute Code must be unique");
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', array('mapping_id' => $this->getRequest()->getParam('mapping_id')));
                return;
            }

            // Save
            $model = Mage::getModel('tnw_salesforce/mapping');
            $model->setData($data)
                ->setId($this->getRequest()->getParam('mapping_id'));

            try {
                $model->save();
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('tnw_salesforce')->__('Mapping was successfully saved'));
                Mage::getSingleton('adminhtml/session')->setFormData(false);
                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', array('mapping_id' => $model->getId()));
                    return;
                }
                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', array('mapping_id' => $this->getRequest()->getParam('mapping_id')));
                return;
            }
        }
        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Unable to find mapping to save'));
        $this->_redirect('*/*/');
    }

    /**
     * Delete Action
     *
     */
    public function deleteAction()
    {
        if ($this->getRequest()->getParam('mapping_id') > 0) {
            try {
                $model = Mage::getModel('tnw_salesforce/mapping');

                $model->setId($this->getRequest()->getParam('mapping_id'))
                    ->delete();

                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('tnw_salesforce')->__('Mapping was successfully deleted'));
                $this->_redirect('*/*/');
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/edit', array('mapping_id' => $this->getRequest()->getParam('mapping_id')));
            }
        }
        $this->_redirect('*/*/');
    }

    public function massDeleteAction()
    {
        $itemIds = $this->getRequest()->getParam('lead');
        if (!is_array($itemIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Please select mapping(s)'));
        } else {
            try {
                foreach ($itemIds as $itemId) {
                    $item = Mage::getModel('tnw_salesforce/mapping')->load($itemId);
                    $item->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__(
                        'Total of %d record(s) were successfully deleted', count($itemIds)
                    )
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    protected function _validate($data)
    {
        if (!$id = $this->getRequest()->getParam('mapping_id')) {
            return true;
        }
        $collection = Mage::getModel('tnw_salesforce/mapping')->getCollection();
        $collection->getSelect()
            ->where('main_table.local_field = ?', $data['local_field'])
            ->where('main_table.sf_object = ?', $data['sf_object'])
            ->where('main_table.sf_field = ?', $data['sf_field']);

        foreach ($collection as $_item) {
            if ($_item->getMappingId() != $id) {
                //Found a duplicate!
                return false;
            }
        }

        return true;
    }
}
