<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Abandoned_Opportunitylineitem_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('opportunitylineitemGrid');
        $this->setDefaultSort('mapping_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('tnw_salesforce/mapping')->getCollection()
            ->addObjectToFilter(TNW_Salesforce_Model_Config_Objects::ABANDONED_ITEM_OBJECT);
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('mapping_id', array(
            'header' => Mage::helper('tnw_salesforce')->__('Mapping ID'),
            'width' => '1',
            'index' => 'mapping_id',
        ));

        $this->addColumn('local_field', array(
            'header' => Mage::helper('tnw_salesforce')->__('Magento Field'),
            'index' => 'local_field',
            'width' => '300',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Mapping_Magento(),
        ));

        $this->addColumn('sf_field', array(
            'header' => Mage::helper('tnw_salesforce')->__('Salesforce Field API Name'),
            'width' => '250',
            'index' => 'sf_field',
        ));

        $this->addColumn('default_value', array(
            'header' => Mage::helper('tnw_salesforce')->__('Default Value'),
            'index' => 'default_value',
        ));


        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('mapping_id');
        $this->getMassactionBlock()->setFormFieldName('opportunitylineitem');

        $this->getMassactionBlock()->addItem('delete', array(
            'label' => Mage::helper('tnw_salesforce')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('tnw_salesforce')->__('Are you sure?')
        ));

        return $this;
    }

    /**
     * Row click url
     *
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', array('mapping_id' => $row->getId()));
    }
}
