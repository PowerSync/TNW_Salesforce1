<?php

class TNW_Salesforce_Adminhtml_Salesforce_Order_ShipmentitemController extends TNW_Salesforce_Controller_Base_Mapping
{
    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_ITEM_OBJECT;

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = 'OrderShipmentItem';

    /**
     * path to the blocks which will be rendered by
     * @var string
     */
    protected $_blockPath = 'mapping_shipment_ordershipmentitem';

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('tnw_salesforce/mappings/shipment_mapping/order_shipment_item');
    }
}
