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
        $_mMagentoId = parent::getMagentoId($object, $_sSalesforceId);
        if (!empty($_sSalesforceId) && is_null($_mMagentoId)) {
            // Try to find the user by SF Id
            $orderTable = Mage::helper('tnw_salesforce')->getTable('sales_flat_order');
            $sql = "SELECT increment_id FROM `$orderTable` WHERE opportunity_id = '$_sSalesforceId'";
            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $_mMagentoId = $row['increment_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Order #{$_mMagentoId} Loaded by using Opportunity ID: {$_sSalesforceId}");
            }
        }

        if (!$_mMagentoId) {
            $_sMagentoIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . 'Magento_ID__c';
            $_sMagentoId    = (property_exists($object, $_sMagentoIdKey) && $object->$_sMagentoIdKey)
                ? $object->$_sMagentoIdKey : null;

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('SKIPPING: could not find the order by number: '. $_sMagentoId);
            return false;
        }
        return $_mMagentoId;
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

    /**
     * @param Mage_Sales_Model_Resource_Order_Item_Collection $orderItemCollection
     * @return array
     */
    protected function salesforceIdsByOrderItems($orderItemCollection)
    {
        return $orderItemCollection->walk('getOpportunityId');
    }
}