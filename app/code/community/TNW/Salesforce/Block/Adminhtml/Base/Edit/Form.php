<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Block_Adminhtml_Base_Edit_Form
 */
class TNW_Salesforce_Block_Adminhtml_Base_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{

    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = '';

    /**
     * type Magento attributes
     * @var string
     */
    protected $_tmAttributes = '';

    /**
     * array of fields which should be hidden
     * @var array
     */
    protected $_hiddenFields = array();

    /**
     * @return array
     */
    public function getHiddenFields()
    {
        return $this->_hiddenFields;
    }

    /**
     * @param array $hiddenFields
     * @return $this
     */
    public function setHiddenFields($hiddenFields)
    {
        $this->_hiddenFields = $hiddenFields;
        return $this;
    }

    /**
     * @param array $hiddenFields
     * @return $this
     */
    public function addHiddenField($hiddenFields)
    {
        if (!is_array($hiddenFields)) {
            $hiddenFields = array($hiddenFields);
        }

        foreach ($hiddenFields as $hiddenField) {
            $this->_hiddenFields[] = $hiddenField;
        }
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
     * @return string
     */
    public function getTypeAttribute()
    {
        if (empty($this->_tmAttributes)) {
            return $this->getSfEntity(true);
        }

        return $this->_tmAttributes;
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
     * @param $name
     * @return bool
     */
    protected function _hideField($name)
    {
        if (in_array($name, $this->_hiddenFields)) {
            return true;
        }
        return false;
    }

    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array(
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', array('mapping_id' => $this->getRequest()->getParam('mapping_id'))),
            'method' => 'post',
            'enctype' => 'multipart/form-data'
        ));

        $form->setUseContainer(true);
        $this->setForm($form);

        $formData = Mage::registry(sprintf('salesforce_%s_data', $this->getSfEntity()))->getData();
        $_isSystem = isset($formData['is_system'])
            ? (bool)$formData['is_system'] : false;

        if (isset($formData['default_value']) && $formData['default_value']) {
            $locAttr = explode(" : ", $formData['local_field']);
            $formData['default_code'] = end($locAttr);
            array_pop($locAttr);
            array_push($locAttr, "field");
            $formData['local_field'] = join(" : ", $locAttr);
            Mage::registry(sprintf('salesforce_%s_data', $this->getSfEntity()))->setData($formData);
        }

        $sfFields = array();
        $helper = Mage::helper('tnw_salesforce/salesforce_data');
        foreach ($helper->getAllFields($this->getSfEntity(true)) as $key => $field) {
            if ($this->_hideField($key)) {
                continue;
            }

            $sfFields[] = array(
                'value' => $key,
                'label' => $field
            );
        }

        if (empty($sfFields)) {
            $sfFields[] = array(
                'value' => '',
                'label' => 'No custom fields found in Salesforce'
            );
        }

        $_typeValues = Mage::getModel('tnw_salesforce/config_mapping')->toArray();
        if ($_isSystem) {
            unset($_typeValues[TNW_Salesforce_Model_Mapping::SET_TYPE_UPDATE]);
        }

        /* Mapping Information */
        $fieldset = $form->addFieldset($this->getSfEntity() . '_map', array('legend' => $this->__('Mapping Information')));

        $fieldset->addField('sf_field', 'select', array(
            'label' => $this->__('Salesforce Name'),
            'name' => 'sf_field',
            'note' => $this->__('Salesforce API field name.'),
            'style' => 'width:400px',
            'values' => $sfFields,
            'class' => 'chosen-select',
            'disabled' => $_isSystem
        ));

        $fieldset->addField('local_field', 'select', array(
            'label' => $this->__('Local Name'),
            'class' => 'required-entry chosen-select',
            'note' => $this->__('Choose Magento field you wish to map to Salesforce API.'),
            'style' => 'width:400px',
            'name' => 'local_field',
            'values' => Mage::helper('tnw_salesforce/magento')->getMagentoAttributes($this->getTypeAttribute()),
            'disabled' => $_isSystem
        ));

        /* Magento > SF */
        $fieldset = $form->addFieldset('magento_sf', array('legend' => $this->__('Magento > Salesforce Settings')));

        $_magentoSfEnable = $fieldset->addField('magento_sf_enable', 'select', array(
            'label' => $this->__('Enable'),
            'name' => 'magento_sf_enable',
            'class' => 'chosen-select',
            'values' => Mage::getModel('adminhtml/system_config_source_yesno')->toArray(),
            'note' => 'Allow Magento to change data in Salesforce for this mapping',
            'disabled' => $_isSystem
        ));

        $_magentoSfType = $fieldset->addField('magento_sf_type', 'select', array(
            'label' => $this->__('When'),
            'name' => 'magento_sf_type',
            'class' => 'chosen-select',
            'values' => $_typeValues,
            'note' => '<b>Upsert</b> - update value when inserting or creating record<br>'
                . '<b>Insert Only</b> - pass value to Salesforce only when creating a new record<br>'
                . '<b>Update Only</b> - pass value to Salesforce only when updating a record',
        ));

        /* SF > Magento */
        $fieldset = $form->addFieldset('sf_magento', array('legend' => $this->__('Salesforce > Magento Settings')));

        $_sfMagentoEnable = $fieldset->addField('sf_magento_enable', 'select', array(
            'label' => $this->__('Enable'),
            'name' => 'sf_magento_enable',
            'class' => 'chosen-select',
            'values' => Mage::getModel('adminhtml/system_config_source_yesno')->toArray(),
            'note' => 'Allow Magento to change data in Salesforce for this mapping',
            'disabled' => $_isSystem
        ));

        $_sfMagentoType = $fieldset->addField('sf_magento_type', 'select', array(
            'label' => $this->__('When'),
            'name' => 'sf_magento_type',
            'class' => 'chosen-select',
            'values' => $_typeValues,
            'note' => '<b>Upsert</b> - update value when inserting or creating record<br>'
                . '<b>Insert Only</b> - pass value to Salesforce only when creating a new record<br>'
                . '<b>Update Only</b> - pass value to Salesforce only when updating a record',
        ));

        /* Custom Value */
        $fieldset = $form->addFieldset('contact_map_custom', array('legend' => $this->__('Custom Mapping')));

        $fieldset->addField('default_code', 'text', array(
            'label' => $this->__('Attribute Code'),
            'note' => $this->__('Unique attribute code.'),
            'style' => 'width:400px',
            'name' => 'default_code',
            'disabled' => $_isSystem
        ));

        $fieldset->addField('default_value', 'text', array(
            'label' => $this->__('Attribute Value'),
            'note' => $this->__('Value to be used when Object is created'),
            'style' => 'width:400px',
            'name' => 'default_value',
            'disabled' => $_isSystem
        ));

        /** @var mage_adminhtml_block_widget_form_element_dependence $_formElementDependence */
        $_formElementDependence = $this->getLayout()
            ->createBlock('adminhtml/widget_form_element_dependence');

        $_formElementDependence
            ->addFieldMap($_magentoSfEnable->getId(), 'sf_enable')
            ->addFieldMap($_magentoSfType->getId(), 'sf_type')
            ->addFieldDependence('sf_type', 'sf_enable', '1');

        $_formElementDependence
            ->addFieldMap($_sfMagentoEnable->getId(), 'mg_enable')
            ->addFieldMap($_sfMagentoType->getId(), 'mg_type')
            ->addFieldDependence('mg_type', 'mg_enable', '1');

        /*$_formElementDependence
            ->addConfigOptions(array('can_edit_price'=> false, 'levels_up'=> 1))*/;

        $this->setChild('form_after', $_formElementDependence);

        if (Mage::getSingleton('adminhtml/session')->getData($this->getSfEntity() . '_data')) {
            $form->setValues(Mage::getSingleton('adminhtml/session')->getData($this->getSfEntity() . '_data'));
            Mage::getSingleton('adminhtml/session')->setData($this->getSfEntity() . '_data', null);
        } elseif (Mage::registry(sprintf('salesforce_%s_data', $this->getSfEntity()))) {
            $form->setValues(Mage::registry(sprintf('salesforce_%s_data', $this->getSfEntity()))->getData());
        }
        return parent::_prepareForm();
    }

}
