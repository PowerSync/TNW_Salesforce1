<?php
/**
 * Order history block
 *
 * @category   TNW
 * @package    TNW_Salesforce
 * @author      Powersync Core Team <support@powersync.biz>
 */
class TNW_Salesforce_Block_Sales_Order_View_Salesforce extends Mage_Adminhtml_Block_Sales_Order_Abstract
{
    /**
     * Return array of additional account data
     * Value is option style array
     *
     * @return array
     */
    public function getSalesforceData()
    {
        $_labels = Mage::helper('tnw_salesforce/field')->getOrderLabels();
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
