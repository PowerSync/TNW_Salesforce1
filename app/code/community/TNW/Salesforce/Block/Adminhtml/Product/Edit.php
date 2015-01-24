<?php

/**
 * Class TNW_Salesforce_Block_Adminhtml_Product_Edit
 */
class TNW_Salesforce_Block_Adminhtml_Product_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        if (!Mage::helper('tnw_salesforce')->isWorking()) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('There is an issue with integration, please make sure all tests are successful!'));
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')));
            return;
        }
        $this->_objectId = 'mapping_id';
        parent::__construct();

        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_product';

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
            $('product_map_custom').hide();
            $$('#local_field option').each(function(o){
            	if (o.selected && o.readAttribute('value') == 'Custom : field') {
            		$('product_map_custom').show();
    			}
    		});
            $('local_field').observe('change',function(){
            	$('product_map_custom').hide();
            	$$('#local_field option').each(function(o){
            		if (o.selected && o.readAttribute('value') == 'Custom : field') {
            			$('product_map_custom').show();
    				}
    			});
    		});
        ";
    }

    /**
     * @return mixed
     */
    protected function getProduct()
    {
        return Mage::registry('salesforce_product_data');
    }

    /**
     * Return translated header text depending on creating/editing action
     *
     * @return string
     */
    public function getHeaderText()
    {
        if ($this->getProduct()->getId()) {
            return Mage::helper('tnw_salesforce')->__('%s Object Mapping #%s', $this->htmlEscape($this->getProduct()->getSfObject()), $this->getProduct()->getId());
        } else {
            return Mage::helper('tnw_salesforce')->__('New Product Mapping');
        }
    }
}