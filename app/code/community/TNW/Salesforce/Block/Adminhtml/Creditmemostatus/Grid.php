<?php

class TNW_Salesforce_Block_Adminhtml_Creditmemostatus_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('creditMemoStatusGrid');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('tnw_salesforce/order_creditmemo_status_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('status_id', array(
            'header'    => $this->__('Mapping ID'),
            'width'     => '1',
            'index'     => 'status_id',
        ));

        $this->addColumn('magento_stage', array(
            'header'    => $this->__('Magento Status'),
            'index'     => 'magento_stage',
            'type'      => 'options',
            'options'   => Mage::getModel('sales/order_creditmemo')->getStates(),
        ));

        $this->addColumn('salesforce_status', array(
            'header'    => $this->__('Salesforce Status'),
            'index'     => 'salesforce_status',
            'sortable'  => false,
            'filter'    => false,
            'type'      => 'text',
        ));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('status_id');
        $this->getMassactionBlock()->setFormFieldName('status_ids');

        $this->getMassactionBlock()
            ->addItem('delete', array(
                'label' => $this->__('Delete'),
                'url' => $this->getUrl('*/*/massDelete'),
                'confirm' => $this->__('Are you sure?')
            ));

        return $this;
    }

    /**
     * Row click url
     *
     * @param $row tnw_salesforce_model_order_creditmemo_status
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', array('status_id' => $row->getId()));
    }
}
