<?php

class TNW_Salesforce_Block_Adminhtml_Catalog_Product_Edit extends Mage_Adminhtml_Block_Catalog_Product_Edit
{
    protected function _prepareLayout()
    {
        $this->setChild('sf_sync_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
                    'label'     => Mage::helper('tnw_salesforce')->__('Synchronize w/ Salesforce'),
                    'onclick'   => 'setLocation(\''
                        . $this->getUrl('*/salesforcesync_productsync/sync', array('product_id' => $this->getProductId())).'\')',
                ))
        );

        return parent::_prepareLayout();
    }

    public function getBackButtonHtml()
    {
        if (!Mage::helper('tnw_salesforce/config_product')->isEnabledProductSync()) {
            return parent::getBackButtonHtml();
        }

        return parent::getBackButtonHtml() . $this->getChildHtml('sf_sync_button');
    }
}