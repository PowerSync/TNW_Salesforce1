<?php

class TNW_Salesforce_Adminhtml_Salesforce_OrdercreditmemoitemController extends TNW_Salesforce_Controller_Base_Mapping
{
    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = 'OrderItem';

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = 'OrderCreditMemoItem';

    /**
     * path to the blocks which will be rendered by
     * @var string
     */
    protected $_blockPath = 'mapping_creditmemo_ordercreditmemoitem';

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('tnw_salesforce/mappings/creditmemo_mapping/order_creditmemo_item_mapping');
    }
}
