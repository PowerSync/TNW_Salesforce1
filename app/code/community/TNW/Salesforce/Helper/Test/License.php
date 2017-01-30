<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Test_License extends TNW_Salesforce_Helper_Test_Abstract
{
    const VALIDATE_PATH     = 'tnw_salesforce/test/license_last_time';
    const VALIDATE_LIFETIME = 86400;

    /**
     * @var string
     */
    protected $_title = 'Powersync&#153; license validation';

    /**
     * @var string
     */
    protected $_message = 'Your license is unavailable or has expired';

    /**
     * @var
     */
    protected $_redirect;

    /**
     * @return bool
     */
    protected function _performTest()
    {
        $_model = TNW_Salesforce_Model_Connection::createConnection();
        return $_model->checkPackage();
    }

    /**
     * @return Mage_Core_Model_Config_Data
     */
    protected static function loadConfigObject()
    {
        /** @var Mage_Core_Model_Website $website */
        $website = Mage::helper('tnw_salesforce/config')
            ->getWebsiteDifferentConfig();

        $scopeId = $website->getId();
        $scope = ($scopeId == Mage_Core_Model_App::ADMIN_STORE_ID)
            ? 'default' : 'website';

        /** @var Mage_Core_Model_Mysql4_Config_Data_Collection $configCollection */
        $configCollection = Mage::getResourceModel('core/config_data_collection');
        $configCollection
            ->addFieldToFilter('path', self::VALIDATE_PATH)
            ->addFieldToFilter('scope', $scope)
            ->addFieldToFilter('scope_id', $scopeId);

        /** @var Mage_Core_Model_Config_Data $config */
        $config = $configCollection->getFirstItem();
        return $config->addData(array(
            'path' => self::VALIDATE_PATH,
            'scope' => $scope,
            'scope_id' => $scopeId,
        ));
    }

    /**
     * @return bool
     */
    public static function isValidate()
    {
        $lastRunTime = (int)self::loadConfigObject()->getData('value');
        return time() - $lastRunTime > self::VALIDATE_LIFETIME;
    }

    /**
     *
     */
    public static function validateDateUpdate()
    {
        self::loadConfigObject()
            ->setData('value', time())
            ->save();
    }

    /**
     *
     */
    public static function validateDateReset()
    {
        self::loadConfigObject()
            ->setData('value', null)
            ->save();
    }
}