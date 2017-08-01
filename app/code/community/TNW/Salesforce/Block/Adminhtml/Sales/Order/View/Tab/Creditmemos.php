<?php

class TNW_Salesforce_Block_Adminhtml_Sales_Order_View_Tab_Creditmemos extends Mage_Adminhtml_Block_Sales_Order_View_Tab_Creditmemos
{
    protected function _prepareCollection()
    {
        /** @var mage_sales_model_resource_order_shipment_grid_collection $collection */
        $collection = Mage::getResourceModel($this->_getCollectionClass())
            ->addFieldToSelect('entity_id')
            ->addFieldToSelect('created_at')
            ->addFieldToSelect('increment_id')
            ->addFieldToSelect('order_currency_code')
            ->addFieldToSelect('store_currency_code')
            ->addFieldToSelect('base_currency_code')
            ->addFieldToSelect('state')
            ->addFieldToSelect('grand_total')
            ->addFieldToSelect('base_grand_total')
            ->addFieldToSelect('billing_name')
            ->addFieldToSelect('store_id')
        ;

        $collection->join(array('flat_creditmemo' => 'sales/creditmemo'),
            'main_table.entity_id = flat_creditmemo.entity_id',
            array('salesforce_id', 'sf_insync'));

        $collection->addFieldToFilter('main_table.order_id', $this->getOrder()->getId());

        $this->setCollection($collection);
        return Mage_Adminhtml_Block_Widget_Grid::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        if (Mage::helper('tnw_salesforce')->isProfessionalEdition()) {
            $this->addColumn('sf_insync', array(
                'header' => Mage::helper('sales')->__('Status'),
                'width' => '40px',
                'type' => 'options',
                'options' => array(
                    0 => 'No',
                    1 => 'Yes',
                ),
                'index' => 'sf_insync',
                'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Status()
            ));

            $this->addColumn('salesforce_id', array(
                'header' => Mage::helper('sales')->__('Salesforce ID'),
                'index' => 'salesforce_id',
                'type' => 'text',
                'width' => '140px',
                'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Id(),
            ));

            $this->addColumnAfter('singleAction', array(
                'header' => Mage::helper('sales')->__('Action'),
                'width' => '50px',
                'type' => 'action',
                'getter' => 'getId',
                'actions' => array(
                    array(
                        'caption' => Mage::helper('sales')->__('Sync'),
                        'url' => array('base' => '*/salesforcesync_creditmemosync/sync'),
                        'field' => 'creditmemo_id'
                    )
                ),
                'filter' => false,
                'sortable' => false,
                'index' => 'stores',
                'is_system' => true,
            ), 'base_grand_total');
        }

        return parent::_prepareColumns();
    }
}