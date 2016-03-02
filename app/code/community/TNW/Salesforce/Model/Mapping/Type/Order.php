<?php

class TNW_Salesforce_Model_Mapping_Type_Order extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Order';

    /**
     * @param $_entity Mage_Sales_Model_Order
     * @return string
     */
    public function getValue($_entity)
    {
        $attribute = $this->_mapping->getLocalFieldAttributeCode();
        $type = $this->_mapping->getLocalFieldType();
        if ($type == 'Customer' && $attribute == 'email') {
            return $_entity->getCustomerEmail();
        } elseif ($type == 'Order') {
            switch ($attribute) {
                case 'cart_all':
                    $class = Mage::getConfig()->getModelClassName('tnw_salesforce/sync_mapping_order_base');
                    return $class::getOrderDescription($_entity);
                case 'number':
                    return $_entity->getIncrementId();
                case 'payment_method':
                    $value = '';
                    if ($_entity->getPayment()) {
                        $paymentMethods = Mage::helper('payment')->getPaymentMethodList(true);
                        $method = $_entity->getPayment()->getMethod();
                        if (array_key_exists($method, $paymentMethods)) {
                            $value = $paymentMethods[$method];
                        } else {
                            $value = $method;
                        }
                    }
                    return $value;
                case 'notes':
                    $allNotes = '';
                    foreach ($_entity->getStatusHistoryCollection() as $historyItem) {
                        $comment = trim(strip_tags($historyItem->getComment()));
                        if (!$comment || empty($comment)) {
                            continue;
                        }
                        $allNotes .= Mage::helper('core')->formatTime($historyItem->getCreatedAtDate(), 'medium')
                            . " | " . $historyItem->getStatusLabel() . "\n";
                        $allNotes .= strip_tags($historyItem->getComment()) . "\n";
                        $allNotes .= "-----------------------------------------\n\n";
                    }
                    return empty($allNotes) ? null : $allNotes;

                case 'website':
                    return '';
            }
        } elseif ($type == 'Aitoc') {
            //TODO: Write a unit test for this part
            $modules = Mage::getConfig()->getNode('modules')->children();
            if (property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
                $aCustomAttrList = Mage::getModel('aitcheckoutfields/transport')->loadByOrderId($_entity->getId());
                foreach ($aCustomAttrList->getData() as $_key => $_data) {
                    if ($_data['code'] == $attribute) {
                        $value = $_data['value'];
                        if ($_data['type'] == "date") {
                            return date("Y-m-d", strtotime($value));
                        }
                    }
                }
            }
        }

        return parent::getValue($_entity);
    }
}