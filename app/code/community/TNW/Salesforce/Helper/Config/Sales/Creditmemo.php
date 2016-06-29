<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Sales_Creditmemo extends TNW_Salesforce_Helper_Config_Sales
{
    const CREDIT_MEMO_SYNC_ENABLE = 'salesforce_order/creditmemo_configuration/sync_enabled';

    // Allow Magento to synchronize invoices with Salesforce
    public function syncCreditMemo()
    {
        return (int)$this->getStoreConfig(self::CREDIT_MEMO_SYNC_ENABLE);
    }

    /**
     * @return bool
     */
    public function syncCreditMemoForOrder()
    {
        return $this->syncCreditMemo()
        && self::SYNC_TYPE_ORDER == strtolower($this->_helper()->getOrderObject());
    }
}