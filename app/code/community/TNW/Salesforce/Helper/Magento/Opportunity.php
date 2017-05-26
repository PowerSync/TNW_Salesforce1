<?php

class TNW_Salesforce_Helper_Magento_Opportunity extends TNW_Salesforce_Helper_Magento_Order_Base
{
    /**
     * @var string
     */
    protected $_mappingEntityName = 'Opportunity';

    /**
     * @var string
     */
    protected $_mappingEntityItemName = 'OpportunityLineItem';

    /**
     * @comment salesforce entity alias
     * @var string
     */
    protected $_salesforceEntityName = 'opportunity';

    /**
     * @param null $object
     * @param $_sSalesforceId
     * @return bool|null
     */
    public function getMagentoId($object = null, $_sSalesforceId)
    {
        $_sMagentoId = parent::getMagentoId($object, $_sSalesforceId);
        if (!$_sMagentoId) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('SKIPPING: could not find the order by number: '. $_sMagentoId);
            return false;
        }
        return $_sMagentoId;
    }

    protected function _updateMagento($object, $_sMagentoId, $_sSalesforceId)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')
            ->loadByIncrementId($_sMagentoId);

        $order->addData(array(
            'opportunity_id' => $_sSalesforceId,
            'sf_insync'     => 1
        ));

        $this->_updateMappedEntityFields($object, $order)
            ->_updateMappedEntityItemFields($object, $order)
            ->_updateNotes($object, $order)
            ->saveEntities();

        return $order;
    }

}