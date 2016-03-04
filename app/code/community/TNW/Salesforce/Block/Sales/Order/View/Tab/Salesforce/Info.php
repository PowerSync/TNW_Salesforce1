<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Sales_Order_View_Tab_Salesforce_Info extends Mage_Adminhtml_Block_Sales_Order_Abstract
{
    /**
     * Return array of additional account data
     * Value is option style array
     *
     * @return array
     */
    public function getSalesforceData()
    {
        $_labels = Mage::helper('tnw_salesforce/config_sales_order')->getOrderLabels();
        $sfData = array();
        if ($this->doesHaveOrder()) {
            foreach ($_labels as $_field => $_label) {
                $_value = $this->getOrder()->getData($_field);
                if ($_value) {
                    $sfData[] = array(
                        'label' => $_label,
                        'value' => Mage::helper('tnw_salesforce/salesforce_abstract')->generateLinkToSalesforce($this->escapeHtml($_value, array('br')))
                    );
                }
            }
            //ksort($accountData, SORT_NUMERIC);
        }

        return $sfData;
    }

    public function doesHaveOrder() {
        try {
            $orderExists = ($this->getOrder() && is_object($this->getOrder())) ? true : false;
        } catch (Exception $e) {
            $orderExists = false;
        }

        return $orderExists;
    }
}
