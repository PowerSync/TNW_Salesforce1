<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Sales_Creditmemo extends TNW_Salesforce_Helper_Config_Sales
{
    const CREDIT_MEMO_SYNC_ENABLE = 'salesforce_creditmemo/creditmemo_configuration/sync_enabled';
    const CREDIT_MEMO_NOTES_SYNC  = 'salesforce_creditmemo/creditmemo_configuration/notes_synchronize';

    /**
     * @return bool
     * @deprecated
     */
    public function syncCreditMemo()
    {
        return $this->autoSyncCreditMemo();
    }

    /**
     * @return bool
     */
    public function autoSyncCreditMemo()
    {
        return (bool)$this->getStoreConfig(self::CREDIT_MEMO_SYNC_ENABLE);
    }

    /**
     * @return bool
     */
    public function syncCreditMemoNotes()
    {
        return (int)$this->getStoreConfig(self::CREDIT_MEMO_NOTES_SYNC);
    }

    /**
     * @return bool
     */
    public function syncCreditMemoForOrder()
    {
        return Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed();
    }
}