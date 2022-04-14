<?php

class TNW_Salesforce_Model_System_Config_Source_Shipping_Methods
{
    /**
     * @return array
     */
    public function toOptionArray()
    {

        $methods['tnw'] = array(
            'label' => sprintf('%s [%s]', 'TNW', 'tnw'),
            'value' => array(
                'import' => array(
                    'label' => sprintf('%s [%s]', 'Custom method for Import', 'import'),
                    'value' => sprintf('%s:::%s', 'tnw', 'import'),
                )
            ),
        );

        $carriers = Mage::getSingleton('shipping/config')->getActiveCarriers();
        foreach ($carriers as $carrierCode => $carrierModel) {
            if (!$carrierModel->isActive()) {
                continue;
            }
            $carrierMethods = $carrierModel->getAllowedMethods();
            if (!$carrierMethods) {
                continue;
            }
            $carrierTitle = Mage::getStoreConfig('carriers/' . $carrierCode . '/title');

            $methods[$carrierCode] = array(
                'label' => sprintf('%s [%s]', $carrierTitle, $carrierCode),
                'value' => array(),
            );
            foreach ($carrierMethods as $methodCode => $methodTitle) {
                $methods[$carrierCode]['value'][$methodCode] = array(
                    'label' => sprintf('%s [%s]', $methodTitle, $methodCode),
                    'value' => sprintf('%s:::%s', $carrierCode, $methodCode),
                );
            }
        }

        return $methods;
    }
}
