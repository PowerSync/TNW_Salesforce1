<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Queue_From_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    protected function _construct()
    {
        parent::_construct();
        $this->setId('tnw_salesforce_queuesync_grid');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('desc');
    }

    /**
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('tnw_salesforce/import')->getCollection();
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * @return $this
     */
    protected function _prepareColumns()
    {
        $this->addColumn('created_at', array(
            'header' => Mage::helper('tnw_salesforce')->__('Date Created'),
            'index' => 'created_at',
            'type' => 'datetime',
            'width' => '100px',
        ));

        $this->addColumn('object_id', array(
            'header' => Mage::helper('tnw_salesforce')->__('Object Id'),
            'index' => 'object_id',
            'type' => 'varchar',
            'width' => '140px',
            'renderer' => 'tnw_salesforce/adminhtml_renderer_link_salesforce_id',
        ));

        $this->addColumn('object_type', array(
            'header' => Mage::helper('tnw_salesforce')->__('Object Type'),
            'type' => 'text',
            'index' => 'object_type'
        ));

        $this->addColumn('status', array(
            'header' => Mage::helper('tnw_salesforce')->__('Status'),
            'type' => 'options',
            'options' => array(
                TNW_Salesforce_Model_Import::STATUS_NEW         => $this->__('New'),
                TNW_Salesforce_Model_Import::STATUS_PROCESSING  => $this->__('Processing'),
                TNW_Salesforce_Model_Import::STATUS_SUCCESS     => $this->__('Success'),
                TNW_Salesforce_Model_Import::STATUS_ERROR       => $this->__('Error'),
            ),
            'index' => 'status',
        ));

        $this->addColumn('message', array(
            'header' => Mage::helper('tnw_salesforce')->__('Message'),
            'type' => 'text',
            'index' => 'message',
        ));

        $this->addColumn('json', array(
            'header'    => Mage::helper('tnw_salesforce')->__('JSON'),
            'index'     => 'json',
            'renderer'  => 'tnw_salesforce/adminhtml_widget_grid_column_renderer_json'
        ));

        return parent::_prepareColumns();
    }

    /**
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
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