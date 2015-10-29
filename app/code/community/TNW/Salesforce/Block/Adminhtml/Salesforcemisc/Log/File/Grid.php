<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Block_Adminhtml_Salesforcemisc_Log_File_Grid
 */
class TNW_Salesforce_Block_Adminhtml_Salesforcemisc_Log_File_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    protected function _construct()
    {
        $this->setSaveParametersInSession(true);
        $this->setId('logfilesGrid');
        $this->setDefaultSort('time', 'desc');
    }

    /**
     * Init logs collection
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getSingleton('tnw_salesforce/salesforcemisc_log_file_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare mass action controls
     *
     * @return TNW_Salesforce_Block_Adminhtml_Salesforcemisc_Log_File_Grid
     */
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('id');
        $this->getMassactionBlock()->setFormFieldName('ids');

        $this->getMassactionBlock()->addItem('delete', array(
            'label' => Mage::helper('adminhtml')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('tnw_salesforce')->__('Are you sure you want to delete the selected log file(s)?')
        ));

        return $this;
    }

    /**
     * Configuration of grid
     *
     * @return TNW_Salesforce_Block_Adminhtml_Salesforcemisc_Log_File_Grid
     */
    protected function _prepareColumns()
    {
        $this->addColumn('time', array(
            'header' => Mage::helper('tnw_salesforce')->__('Time'),
            'index' => 'date_object',
            'type' => 'datetime',
            'width' => 200
        ));

        $this->addColumn('display_name', array(
            'header' => Mage::helper('tnw_salesforce')->__('Name'),
            'index' => 'display_name',
            'filter' => false,
            'sortable' => true,
            'width' => 350
        ));

        $this->addColumn('size', array(
            'header' => Mage::helper('tnw_salesforce')->__('Size, Bytes'),
            'index' => 'size',
            'type' => 'number',
            'sortable' => true,
            'filter' => false
        ));

        $this->addColumn('view', array(
            'header' => Mage::helper('tnw_salesforce')->__('View'),
            'format' => '<a href="' . $this->getUrl('*/*/view', array('filename' => '$name')) . '">View</a>',
            'index' => 'name',
            'sortable' => false,
            'filter' => false
        ));


        $this->addColumn('download', array(
            'header' => Mage::helper('tnw_salesforce')->__('Download'),
            'format' => '<a href="' . $this->getUrl('*/*/download', array('filename' => '$filename')) . '">$name</a>',
            'index' => 'name',
            'sortable' => false,
            'filter' => false
        ));

        return $this;
    }

}
