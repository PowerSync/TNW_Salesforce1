<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * facade pattern used
 * on later project stage we need possibility to abstract from multiple sf connection classes located in TNW\Salesforce\Helper\Test namespace
 * the idea is to provide simplified interface which delegates calls to corresponding classes in namespace TNW\Salesforce\Helper\Test
 *
 * Class TNW_Salesforce_Helper_Test_Authentication
 * @package TNW\Salesforce\Helper\Test\Authentication
 */
class TNW_Salesforce_Helper_Test_Authentication extends Mage_Core_Helper_Abstract
{
    const AUTHENTICATION_COUNT = 1;

    /**
     * error container
     *
     * @var array
     */
    public static $errorList = array();

    /**
     * user message container
     *
     * @var array
     */
    public static $notificationList = array();

    /**
     * this method makes all checks before we connect from mage to sf api
     *
     * @param bool $fromCli
     * @return bool
     */
    public function mageSfAuthenticate($fromCli = false)
    {
        // reset
        self::$errorList = array();
        self::$notificationList = array();

        // check if extension is enabled
        if (!$this->isEnabledExtension()) {
            self::$errorList[] = 'extension is disabled';
        }

        // validate the license
        if ($this->isEnabledExtension() && !$this->validateLicense()) {
            self::$errorList[] = 'license validation failed';
        }

        // log error
        $this->logError();

        // show notification
        $this->showNotification($fromCli);

        // if error exists - return false
        if (!empty(self::$errorList)) {

            return false;
        }

        return true;
    }

    /**
     * check if extension is enabled in mage config
     *
     * @return bool
     */
    public function isEnabledExtension()
    {
        $res = Mage::helper('tnw_salesforce')->isEnabled();

        return $res;
    }

    /**
     * we return true if data taken from buffer (cache / session) is valid
     * or
     * we return true if we connected and logged in sf successfully
     *
     * @return bool
     */
    public function validateLicense()
    {
        foreach (array('wsdl', 'connection', 'login', 'license') as $testName) {
            if ($this->_checkTest($testName)) {
                continue;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Test \"{$testName}\" failed");
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function validateLogin()
    {
        foreach (array('wsdl', 'connection', 'login') as $testName) {
            if ($this->_checkTest($testName)) {
                continue;
            }

            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Test \"{$testName}\" failed");
            return false;
        }

        return true;
    }

    protected function _checkTest($testName)
    {
        $test = Mage::helper('tnw_salesforce/test_'.$testName)->performTest();
        if ($test && $test->response != "Success!") {
            self::$notificationList[] = $test->response;

            return false;
        }

        return true;
    }

    /**
     * check cache and then session for data - this logic intended to be incapsulated within this method
     * we need to abstract from cache or session storage for our data, that depends of client side settings
     *
     * @param $key
     * @return mixed
     */
    public function getStorage($key)
    {
        /** @var Mage_Core_Model_Website $website */
        $website = Mage::helper('tnw_salesforce/config')->getWebsiteDifferentConfig();
        $key = sprintf('%s_%s', $key, $website->getCode());

        $count = 0;

        do {
            $res = Mage::app()->useCache('tnw_salesforce')
                ? unserialize(Mage::app()->getCache()->load($key))
                : Mage::getSingleton('core/session')->getData($key);

            if (++$count > self::AUTHENTICATION_COUNT) {
                break;
            }
        } while(empty($res) && $this->validateLogin());

        return $res;
    }

    /**
     * set value to cache or session
     *
     * @param $value
     * @param $key
     * @param null $website
     * @return bool
     */
    public function setStorage($value, $key, $website = null)
    {
        /** @var Mage_Core_Model_Website $website */
        $website = Mage::helper('tnw_salesforce/config')->getWebsiteDifferentConfig($website);
        $key = sprintf('%s_%s', $key, $website->getCode());

        if (Mage::app()->useCache('tnw_salesforce')) {
            return Mage::app()->getCache()->save(serialize($value), $key, array("TNW_SALESFORCE"));
        }
        else {
            Mage::getSingleton('core/session')->setData($key, $value);
            return true;
        }
    }

    /**
     * manage show notification variable state, when we show / not message in browser / command line
     *
     * @param bool $fromCli
     * @return bool
     */
    public function showNotification($fromCli = false)
    {
        // is step 3 above fails, show notification on every page w/o having to go though entire verification flow again.
        // You could use another session variable and set it to Yes, when you need to show a notification on every page
        // and then change that session variable to No to hide the message on every page.
        // TODO implement / discuss logic described above
        if (!empty(self::$notificationList)) {
            if (!$fromCli) {
                if (Mage::helper('tnw_salesforce')->displayErrors()) {
                    $text = implode("<br />", self::$notificationList);
                    Mage::getSingleton('core/session')->addNotice($text);
                }
            }
            else {
                $text = implode("\n", self::$notificationList);
                throw new Exception($text);
            }
        }

        return true;
    }

    /**
     * check static storage and if has data - log that error in file
     *
     * @return bool
     */
    public function logError()
    {
        if (!empty(self::$errorList)) {
            foreach (self::$errorList as $line) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($line);
            }
        }

        return true;
    }

    /**
     * before start sync process in command line environment - make one more extra check
     *
     * @return bool
     */
    public function sfApiDummyCall()
    {
        // if "sf session id" exists, try to make a dummy call (get user data, for example)
        // if this call fails, we need to re-establish the session
        // also, if the re-establish call fails, we stop and assume there are other issues and should show notification (in the admin panel only)
        self::$errorList = array();
        try {
            $sfConnection = Mage::helper('tnw_salesforce/salesforce_data')->getClient();
            $result = $sfConnection->getServerTimestamp();
            if ($result instanceof \stdClass && property_exists($result, 'timestamp')) {

                return true;
            }
        }
        catch (Exception $e) {
            self::$errorList[] = 'sfApiDummyCall failed, '.$e->getMessage();
            $this->logError();
        }

        return false;
    }
}