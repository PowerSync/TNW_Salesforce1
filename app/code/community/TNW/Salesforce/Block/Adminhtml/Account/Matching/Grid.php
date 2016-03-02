<?php

class TNW_Salesforce_Block_Adminhtml_Account_Matching_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * @internal
     */
    protected function _construct()
    {
        parent::_construct();

        $this->setId('accountMatchingGrid');
        $this->setDefaultSort('matching_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('tnw_salesforce/account_matching')
            ->getCollection();
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * @return $this
     * @throws Exception
     */
    protected function _prepareColumns()
    {
        $this->addColumn('matching_id', array(
            'header' => Mage::helper('tnw_salesforce')->__('Rule ID'),
            'width' => '1',
            'index' => 'matching_id',
        ));

        $this->addColumn('account_name', array(
            'header' => Mage::helper('tnw_salesforce')->__('Account Name'),
            'index' => 'account_name',
            'width' => '300',
        ));

        $this->addColumn('account_id', array(
            'header' => Mage::helper('tnw_salesforce')->__('Account Id'),
            'width' => '250',
            'index' => 'account_id',
            //'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Mapping_Magento(),
        ));

        $this->addColumn('email_domain', array(
            'header' => Mage::helper('tnw_salesforce')->__('Email Domain'),
            'index' => 'email_domain',
        ));

        $this->addExportType('*/*/exportCsv', Mage::helper('sales')->__('CSV'));

        return parent::_prepareColumns();
    }

    /**
     * @return $this
     */
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('matching_id');
        $this->getMassactionBlock()->setFormFieldName('matching');

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
        return $this->getUrl('*/*/edit', array('matching_id' => $row->getId()));
    }
}
