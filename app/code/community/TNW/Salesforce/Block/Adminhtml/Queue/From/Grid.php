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

        $this->addColumn('is_processing', array(
            'header' => Mage::helper('tnw_salesforce')->__('Is processing'),
            'type' => 'options',
            'options' => array(
                '1' => Mage::helper('catalog')->__('Yes'),
                '0' => Mage::helper('catalog')->__('No'),
            ),
            'index' => 'is_processing',
            'align' => 'center',
        ));

        $this->addColumn('json', array(
            'header'    => Mage::helper('tnw_salesforce')->__('JSON'),
            'index'     => 'json',
            'renderer'  => 'tnw_salesforce/adminhtml_tool_log_grid_column_renderer_message'
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
}