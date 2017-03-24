<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 30.11.15
 * Time: 15:36
 */
class TNW_Salesforce_Controller_Base_Mapping extends Mage_Adminhtml_Controller_Action
{
    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = '';

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = '';

    /**
     * @var string
     */
    protected $_blockGroup = 'tnw_salesforce';

    /**
     * path to the blocks which will be rendered by controller
     * can be usefull if Salesforce entity name and block class name are different
     * @var string
     */
    protected $_blockPath = '';

    /**
     * @return string
     */
    public function getBlockPath()
    {
        if (empty($this->_blockPath)) {
            return $this->getSfEntity();
        }

        return $this->_blockPath;
    }

    /**
     * @param $blockPath
     * @return $this
     */
    public function setBlockPath($blockPath)
    {
        $this->_blockPath = $blockPath;

        return $this;
    }

    /**
     * @param bool|false $uc should we make first letter in upper case?
     * @return string
     */
    public function getSfEntity($uc = false)
    {
        $sfEntity = $this->_sfEntity;
        if (!$uc) {
            $sfEntity = strtolower($sfEntity);
        }
        return $sfEntity;
    }

    /**
     * @param bool|false $uc should we make first letter in upper case?
     * @return string
     */
    public function getLocalEntity($uc = false)
    {
        $localEntity = $this->_localEntity;
        if (empty($localEntity)) {
            return $this->getSfEntity($uc);
        }

        if (!$uc) {
            $localEntity = strtolower($localEntity);
        }
        return $localEntity;
    }

    /**
     * @param string $sfEntity
     * @return $this
     */
    public function setSfEntity($sfEntity)
    {
        $this->_sfEntity = $sfEntity;
        return $this;
    }

    /**
     * @return $this
     */
    protected function _initLayout()
    {
        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('%s Field Mapping', $this->getLocalEntity(true)), Mage::helper('tnw_salesforce')->__('%s Field Mapping', $this->getLocalEntity(true)));

        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');
        return $this;
    }

    /**
     * Index Action
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('%s Field Mapping', $this->getLocalEntity(true)));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock(sprintf('%s/adminhtml_%s', $this->_blockGroup, $this->getBlockPath())));
        $this->renderLayout();
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
        $id = $this->getRequest()->getParam('mapping_id');
        $model = Mage::getModel('tnw_salesforce/mapping')->load($id);
        if ($model->getId() || $id == 0) {
            $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
            if (!empty($data)) {
                $model->setData($data);
            }

            Mage::register(sprintf('salesforce_%s_data', $this->getSfEntity()), $model);

            $this->_initLayout()
                ->getLayout()
                ->getBlock('head')
                ->setCanLoadExtJs(true);

            $this->_addContent($this->getLayout()->createBlock(sprintf('%s/adminhtml_%s_edit', $this->_blockGroup, $this->getBlockPath())));
            $this->renderLayout();
        } else {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Item does not exist'));
            $this->_redirect('*/*/');
        }
    }

    /**
     * Save Action
     */
    public function saveAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            $data['sf_object'] = $this->getLocalEntity(true);

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
            }
            try {

                // Save
                $model = Mage::getModel('tnw_salesforce/mapping');
                $model->setData($data)
                    ->setId($this->getRequest()->getParam('mapping_id'));

                $describe = Mage::helper('tnw_salesforce/salesforce_data')
                    ->describeTable($model->getSfObject());

                /**
                 * try to find SF field
                 */
                $appropriatedField = false;
                if (!empty($describe->fields)) {
                    foreach ($describe->fields as $field) {
                        if (strtolower($field->name) == strtolower($model->getSfField())) {
                            $appropriatedField = $field;
                            break;
                        }
                    }
                }
                if ($appropriatedField) {
                    $errors = array();
                    if (
                        !$appropriatedField->createable
                        && (
                            $model->getMagentoSfType() == TNW_Salesforce_Model_Mapping::SET_TYPE_INSERT
                            || $model->getMagentoSfType() == TNW_Salesforce_Model_Mapping::SET_TYPE_UPSERT
                        )
                    ) {
                        $errors[] = ($model->getSfField() . ' Salesforce field is not creatable. Cannot be used for Magento-to-Salesforce UPSERT/INSERT actions');
                    }

                    if (
                        !$appropriatedField->updateable
                        && (
                            $model->getMagentoSfType() == TNW_Salesforce_Model_Mapping::SET_TYPE_UPDATE
                            || $model->getMagentoSfType() == TNW_Salesforce_Model_Mapping::SET_TYPE_UPSERT
                        )
                    ) {
                        $errors[] = ($model->getSfField() . ' Salesforce field is not updatable. Cannot be used for Magento-to-Salesforce UPSERT/UPDATE actions');
                    }

                    if (!empty($errors)) {
                        throw new Exception(implode("<br/>", $errors));
                    }
                }

                $model->save();

                Mage::getSingleton('adminhtml/session')
                    ->addSuccess(Mage::helper('tnw_salesforce')->__('Mapping was successfully saved'))
                    ->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', array('mapping_id' => $model->getId()));
                    return;
                }

                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                $message = ($e instanceof Zend_Db_Statement_Exception && $e->getCode() == 23000)
                    ? 'Attribute Code must be unique' : $e->getMessage();

                Mage::getSingleton('adminhtml/session')
                    ->addError($message)
                    ->setFormData($data);

                $this->_redirect('*/*/edit', array('mapping_id' => $this->getRequest()->getParam('mapping_id')));
                return;
            }
        }

        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Unable to find mapping to save'));
        $this->_redirect('*/*/');
    }

    /**
     * Delete Action
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
        $itemIds = $this->getRequest()->getParam('ids');
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
}