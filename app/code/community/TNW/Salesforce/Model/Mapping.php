<?php

/**
 * @method string getLocalField
 * @method string getLocalFieldType
 * @method string getLocalFieldAttributeCode
 * @method string getSfField
 * @method string getAttributeId
 * @method string getBackendType
 * @method string getSfObject
 * @method string getDefaultValue
 *
 * Class TNW_Salesforce_Model_Mapping
 */
class TNW_Salesforce_Model_Mapping extends Mage_Core_Model_Abstract
{
    public function getValue(array $objectMappings = array())
    {
        $value = null;
        $attributeCode = $this->getLocalFieldAttributeCode();
        /** @var Mage_Catalog_Model_Product $object */
        $object = isset($objectMappings[$this->getLocalFieldType()])
            ? $objectMappings[$this->getLocalFieldType()] : null;
        if ($this->getLocalFieldType() == 'Aitoc') {
            $collection = isset($objectMappings['Aitoc']) ? $objectMappings['Aitoc'] : null;
            $value = $this->getAitocValues($collection, $attributeCode);
        } elseif ($object) {
            $value = $this->getSpecialValues($objectMappings);
            if (!$value) {
                $method = 'get' . str_replace(" ", "", ucwords(str_replace("_", " ", $attributeCode)));
                $value = $object->$method();

                if (is_array($value)) {
                    $value = implode(' ', $value);
                } elseif ($this->getBackendType() == 'datetime' || $this->getBackendType() == 'timestamp' || $attributeCode == 'created_at') {
                    $value = gmdate(DATE_ATOM, strtotime($value));
                } else {
                    //check if get option text required
                    if (is_object($object->getResource()) && method_exists($object->getResource(), 'getAttribute')
                        && is_object($object->getResource()->getAttribute($attributeCode))
                        && $object->getResource()->getAttribute($attributeCode)->getFrontendInput() == 'select'
                    ) {
                        $value = $object->getResource()->getAttribute($attributeCode)->getSource()->getOptionText($value);
                    }
                }
            }
        } elseif ($this->getLocalFieldType() == 'Custom') {
            $store = isset($objectMappings['Store']) ? $objectMappings['Store'] : null;
            $value = $this->getCustomValue($store);
        }

        return $value;
    }

    public function getAitocValues($aCustomAtrrList, $attributeCode)
    {
        $value = NULL;
        foreach ($aCustomAtrrList as $_type => $_object) {
            if (is_object($aCustomAtrrList[$_type]) && is_array($aCustomAtrrList[$_type]->getData())) {
                $value = $this->getAitocValue($aCustomAtrrList[$_type], $attributeCode);
                if ($value) {
                    break;
                }
            }
        }

        return $value;
    }

    protected function getAitocValue($aitocValueCollection, $attributeCode)
    {
        $value = NULL;
        foreach ($aitocValueCollection->getData() as $_key => $_data) {
            if ($_data['code'] == $attributeCode) {
                $value = $_data['value'];
                if ($_data['type'] == "date") {
                    $value = date("Y-m-d", strtotime($value));
                }
                break;
            }
        }

        return $value;
    }

    protected function getSpecialValues(array $objectMappings = array())
    {
        $attribute = $this->getLocalFieldAttributeCode();
        $type = $this->getLocalFieldType();
        $helper = Mage::helper('tnw_salesforce/mapping');
        if (isset($objectMappings['Order'])) {
            /** @var Mage_Sales_Model_Order $order */
            $order = $objectMappings['Order'];
            if ($type == 'Customer' && $attribute == 'email') {
                return $order->getCustomerEmail();
            } elseif ($type == 'Order') {
                switch ($attribute) {
                    case 'cart_all':
                        return $helper->getOrderDescription($order);
                    case 'number':
                        return $order->getIncrementId();
                    case 'payment_method':
                        $value = '';
                        if ($order->getPayment()) {
                            $paymentMethods = Mage::helper('payment')->getPaymentMethodList(true);
                            $method = $order->getPayment()->getMethod();
                            if (array_key_exists($method, $paymentMethods)) {
                                $value = $paymentMethods[$method];
                            } else {
                                $value = $method;
                            }
                        }
                        return $value;
                    case 'notes':
                        $allNotes = '';
                        foreach ($order->getStatusHistoryCollection() as $historyItem) {
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

                }
            } elseif ($type == 'Aitoc') {
                //TODO: Write a unit test for this part
                $modules = Mage::getConfig()->getNode('modules')->children();
                if (property_exists($modules, 'Aitoc_Aitcheckoutfields')) {
                    $aCustomAttrList = Mage::getModel('aitcheckoutfields/transport')->loadByOrderId($order->getId());
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
        }

        return null;
    }

    /**
     * @param null|int|Mage_Core_Model_Store $store
     * @return bool|null|string
     */
    public function getCustomValue($store = null)
    {
        $store = Mage::app()->getStore($store);
        switch ($this->getLocalFieldAttributeCode()) {
            case 'current_url':
                return Mage::helper('core/url')->getCurrentUrl();
            case 'todays_date':
                return gmdate('Y-m-d');
            case 'todays_timestamp':
                return gmdate(DATE_ATOM);
            case 'end_of_month':
                return gmdate('Y-m-d', mktime(0, 0, 0, date('n') + 1, 0, date('Y')));
            case 'store_view_name':
                return $store->getName();
            case 'store_group_name':
                return is_object($store->getGroup()) ? $store->getGroup()->getName() : null;
            case 'website_name':
                return $store->getWebsite()->getName();
            default:
                return $this->getProcessedDefaultValue();
        }
    }

    /**
     * @return bool|null|string
     * @throws Mage_Core_Exception
     */
    public function getProcessedDefaultValue()
    {
        $value = $this->getDefaultValue();
        switch ($this->getDefaultValue()) {
            case '{{url}}':
                return Mage::helper('core/url')->getCurrentUrl();
            case '{{today}}':
                return gmdate('Y-m-d');
            case '{{end of month}}':
                return gmdate('Y-m-d', mktime(0, 0, 0, date('n') + 1, 0, date('Y')));
            case '{{contact id}}':
                /**
                 * @deprecated
                 */
                return null;
            case '{{store view name}}':
                return Mage::app()->getStore()->getName();
            case '{{store group name}}':
                return Mage::app()->getStore()->getGroup()->getName();
            case '{{website name}}':
                return Mage::app()->getWebsite()->getName();
            default:
                /**
                 * Is it config path
                 */
                if (substr_count($value, '/') > 1) {
                    $value = Mage::getStoreConfig($value);
                }

                return $value;
        }
    }

    protected function _construct()
    {
        parent::_construct();

        $this->_init('tnw_salesforce/mapping');
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();

        $cutLocalField = explode(" : ", $this->getLocalField());
        if (count($cutLocalField) > 1) {
            $this->setLocalFieldType($cutLocalField[0]);
            $this->setLocalFieldAttributeCode($cutLocalField[1]);
        }

        return $this;
    }
}