<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 29.10.15
 * Time: 17:59
 */
class TNW_Salesforce_Block_Adminhtml_Tool_Log_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('grid_id');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('desc');
        $this->setSaveParametersInSession(true);
    }

    /**
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('tnw_salesforce/tool_log')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @return $this
     */
    protected function _prepareColumns()
    {

        $this->addColumn('entity_id',
            array(
                'header' => $this->__('Id'),
                'width' => '50px',
                'type' => 'number',
                'index' => 'entity_id',
            )
        );

        $this->addColumn('level',
            array(
                'header' => $this->__('Log level'),
                'index' => 'level',
                'type' => 'options',
                'options' => TNW_Salesforce_Model_Tool_Log::getAllLevels(),
                'width' => '70px',
            )
        );

        $this->addColumn('transaction_id',
            array(
                'header' => $this->__('Transaction ID'),
                'index' => 'transaction_id',
                'type' => 'text',
                'width' => '100px',
            )
        );

        $this->addColumn('website_id', array(
            'header' => Mage::helper('catalog')->__('Website'),
            'width' => '100px',
            'index' => 'website_id',
            'type' => 'options',
            'options' => array_merge(
                array(0=>Mage::app()->getWebsite('admin')->getName()),
                Mage::getModel('core/website')->getCollection()->toOptionHash()),
        ));

        $this->addColumn('message',
            array(
                'header' => $this->__('Message'),
                'index' => 'message',
                'renderer' => 'tnw_salesforce/adminhtml_tool_log_grid_column_renderer_message'
            )
        );

        $this->addColumn('created_at',
            array(
                'header' => $this->__('Created at'),
                'index' => 'created_at',
                'type' => 'datetime',
                'filter_time' => true,
                'width' => '150px',
            )
        );

        $this->addExportType('*/*/exportCsv', $this->__('CSV'));

        $this->addExportType('*/*/exportExcel', $this->__('Excel XML'));

        return parent::_prepareColumns();
    }

    /**
     * @return $this
     */
    protected function _prepareMassaction()
    {
        $modelPk = Mage::getModel('tnw_salesforce/tool_log')->getResource()->getIdFieldName();
        $this->setMassactionIdField($modelPk);
        $this->getMassactionBlock()->setFormFieldName('ids');
        // $this->getMassactionBlock()->setUseSelectAll(false);
        $this->getMassactionBlock()->addItem('delete', array(
            'label' => $this->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
        ));
        return $this;
    }

    /**
     * Return row url for js event handlers
     *
     * @param Mage_Catalog_Model_Product|Varien_Object
     * @return string
     */
    public function getRowUrl($item)
    {
        return '';
    }
}
