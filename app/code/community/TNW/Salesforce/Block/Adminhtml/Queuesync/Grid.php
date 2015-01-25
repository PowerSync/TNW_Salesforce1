<?php

/**
 * Class TNW_Salesforce_Block_Adminhtml_Queuesync_Grid
 */
class TNW_Salesforce_Block_Adminhtml_Queuesync_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    // internal constructor
    protected function _construct()
    {
        parent::_construct();
        $this->setId('tnw_salesforce_queuesync_grid');
        $this->setDefaultSort('date_created');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setVarNameFilter('filter');
    }

    /**
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('tnw_salesforce/queue_storage')->getCollection();
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * @return $this
     */
    protected function _prepareColumns()
    {
        $this->addColumn('status', array(
            'header' => Mage::helper('sales')->__('Status'),
            'width' => '40px',
            'type' => 'options',
            'options' => array(
                'new' => 'New',
                'sync_running' => 'Sync running',
            ),
            'index' => 'status',
            'renderer' => 'TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Objectstatus'
        ));

        $this->addColumn('object_id', array(
            'header' => Mage::helper('sales')->__('Object Id'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'object_id',
        ));

        $this->addColumn('sf_object_type', array(
            'header' => Mage::helper('sales')->__('Type'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'sf_object_type',
        ));

        $this->addColumn('mage_object_type', array(
            'header' => Mage::helper('sales')->__('Mage object type'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'mage_object_type',
        ));

        $this->addColumn('date_created', array(
            'header' => Mage::helper('sales')->__('Created On'),
            'index' => 'date_created',
            'type' => 'datetime',
            'width' => '200px',
        ));

        $this->addColumn('sync_attempt', array(
            'header' => Mage::helper('sales')->__('Synchronization attempt count'),
            'width' => '80px',
            'type' => 'text',
            'index' => 'sync_attempt',
        ));

        $this->addColumn('date_sync', array(
            'header' => Mage::helper('sales')->__('Last synchronization date'),
            'width' => '200px',
            'type' => 'datetime',
            'index' => 'date_sync',
        ));

        $this->addColumn('message', array(
            'header' => Mage::helper('sales')->__('Salesforce api response message'),
            'type' => 'text',
            'index' => 'message',
            'renderer' => 'TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Queuemessage'
        ));

        $this->addColumn('singleAction',
            array(
                'header' => Mage::helper('sales')->__('Action'),
                'width' => '50px',
                'type' => 'action',
                'getter' => 'getId',
                'actions' => array(
                    array(
                        'caption' => Mage::helper('tnw_salesforce')->__('Delete'),
                        'url' => array('base' => '*/*/delete'),
                        'field' => 'queue_id'
                    ),
                    array(
                        'caption' => Mage::helper('tnw_salesforce')->__('Process'),
                        'url' => array('base' => '*/*/process'),
                        'field' => 'queue_id'
                    )
                ),
                'filter' => false,
                'sortable' => false,
                'index' => 'stores',
                'is_system' => true,
            ));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {

        $this->setMassactionIdField('queue_id');
        $this->getMassactionBlock()->setFormFieldName('queue');

        $url = '*/*/massDelete';
        if (Mage::helper('tnw_salesforce')->getStoreId() != 0) {
            $url .= '/store/' . Mage::helper('tnw_salesforce')->getStoreId();
        }

        $this->getMassactionBlock()->addItem('delete', array(
            'label' => Mage::helper('tnw_salesforce')->__('Delete'),
            'url' => $this->getUrl($url),
            'confirm' => Mage::helper('tnw_salesforce')->__('This will remove all selected items from the queue. Are you sure?')
        ));

        $url = '*/*/massResync';
        if (Mage::helper('tnw_salesforce')->getStoreId() != 0) {
            $url .= '/store/' . Mage::helper('tnw_salesforce')->getStoreId();
        }

        $this->getMassactionBlock()->addItem('resync', array(
            'label' => Mage::helper('tnw_salesforce')->__('Re-sync'),
            'url' => $this->getUrl($url),
            'confirm' => Mage::helper('tnw_salesforce')->__('This will reset all selected items in the queue. Are you sure?')
        ));

        $url = '*/*/massProcess';
        if (Mage::helper('tnw_salesforce')->getStoreId() != 0) {
            $url .= '/store/' . Mage::helper('tnw_salesforce')->getStoreId();
        }

        $this->getMassactionBlock()->addItem('process', array(
            'label' => Mage::helper('tnw_salesforce')->__('Process'),
            'url' => $this->getUrl($url),
            'confirm' => Mage::helper('tnw_salesforce')->__('This will process all selected items in the queue. Are you sure?')
        ));

        return $this;
    }

    /**
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }
}