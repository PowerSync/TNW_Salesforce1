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
        $config = Mage::getModel('core/config_data')
            ->load(self::VALIDATE_PATH, 'path');

        return $config->setData('path', self::VALIDATE_PATH);
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