<?php

class TNW_Salesforce_Helper_Config_Product extends TNW_Salesforce_Helper_Config
{
    const PRICE_ACCURACY = 'salesforce_product/general/price_accuracy';

    /**
     * @return mixed
     */
    public function getPriceAccuracy()
    {
        return $this->getStroreConfig(self::PRICE_ACCURACY);
    }
}