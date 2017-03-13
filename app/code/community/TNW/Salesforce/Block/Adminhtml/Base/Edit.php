<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Block_Adminhtml_Base_Edit
 */
class TNW_Salesforce_Block_Adminhtml_Base_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{

    protected $_blockGroup = 'tnw_salesforce';

    protected $_controller = 'adminhtml_base';

    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = '';

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
     * @param string $sfEntity
     * @return $this
     */
    public function setSfEntity($sfEntity)
    {
        $this->_sfEntity = $sfEntity;
        return $this;
    }

    public function setLayout(Mage_Core_Model_Layout $layout)
    {
        parent::setLayout($layout);

        $form = $this->getChild('form');

        $form->setSfEntity($this->getSfEntity(true));

        return $this;
    }


    /**
     * TNW_Salesforce_Block_Adminhtml_Base_Edit constructor.
     */
    public function __construct()
    {
        $this->_objectId = 'mapping_id';
        parent::__construct();


        $this->_updateButton('save', 'label', Mage::helper('tnw_salesforce')->__('Save Mapping'));
        $this->_updateButton('delete', 'label', Mage::helper('tnw_salesforce')->__('Delete Mapping'));

        $this->_addButton('saveandcontinue', array(
            'label' => Mage::helper('adminhtml')->__('Save And Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class' => 'save',
        ), -100);

        $this->_formScripts[] = "
            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }
        ";
    }

    /**
     * @return mixed
     */
    protected function getMapping()
    {
        return Mage::registry(sprintf('salesforce_%s_data', $this->getSfEntity()));
    }

    /**
     * Return translated header text depending on creating/editing action
     *
     * @return string
     */
    public function getHeaderText()
    {
        if ($this->getMapping()->getId()) {
            return Mage::helper('tnw_salesforce')->__('%s Object Mapping #%s', $this->htmlEscape($this->getMapping()->getSfObject()), $this->getMapping()->getId());
        } else {
            return Mage::helper('tnw_salesforce')->__('New %s Mapping', $this->getMapping()->getSfObject());
        }
    }
}
