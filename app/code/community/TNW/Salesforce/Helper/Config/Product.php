<?php

class TNW_Salesforce_Helper_Config_Product extends TNW_Salesforce_Helper_Config
{
    const PRICE_ACCURACY = 'salesforce_product/general/price_accuracy';
    const SYNC_TYPE_ALL = 'salesforce_product/general/sync_type_all';
    const SYNC_TYPE_ALLOW = 'salesforce_product/general/sync_type_allow';
    const CREATE_DELETE_PRODUCT = 'salesforce_product/general/create_delete_product';

    /**
     * @return mixed
     */
    public function getPriceAccuracy()
    {
        return $this->getStoreConfig(self::PRICE_ACCURACY);
    }

    /**
     * @return bool
     */
    public function isSyncTypeAll()
    {
        return (bool)(int)$this->getStoreConfig(self::SYNC_TYPE_ALL);
    }

    /**
     * @return array
     */
    public function getSyncTypesAllow()
    {
        if ($this->isSyncTypeAll()) {
            return array_keys(Mage::getModel('tnw_salesforce/config_products_type')->toArray());
        }

        return explode(',', (string)$this->getStoreConfig(self::SYNC_TYPE_ALLOW));
    }

    /**
     * @return bool
     */
    public function isCreateDeleteProduct()
    {
        return (bool)(int)$this->getStoreConfig(self::CREATE_DELETE_PRODUCT);
    }
}