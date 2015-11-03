<?php
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
        if (!$this->validateLicense()) {
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
        // check local storage
        $sfSessionId = $this->getStorage('salesforce_session_id');
        if (!empty($sfSessionId)) {

            return true;
        }

        // call server 1 (only if server 1 timed out or has non valid json response, call server 2)
        // cache result (if false, don't attempt to login into sf)
        $licenseIsValid = Mage::getSingleton('tnw_salesforce/license')->getStatus();
        if (!$licenseIsValid) {

            return false;
        }

        $connectionOk = $this->establishSfConnection();
        if (!$connectionOk) {

            return false;
        }

        // if connection established, try to login (if login failed, stop and show notification)
        $loginOk = $this->loginSf();
        if (!$loginOk) {

            return false;
        }

        return true;
    }

    /**
     * login to sf
     *
     * @return bool
     */
    public function loginSf()
    {
        // if login is good, save "salesforce url" and "session id" into cache
        // we are also saving Org Id, don't forget about that one
        $login = Mage::helper('tnw_salesforce/test_login')->performTest();
        if (!$login) {
            self::$notificationList[] = 'Salesforce login failed';

            return false;
        }

        return true;
    }

    /**
     * create sf connection
     *
     * @return bool
     */
    public function establishSfConnection()
    {
        // do pre-checks and try to establish connection (if failed, stop and show notification)
        $connection = Mage::helper('tnw_salesforce/test_connection')->performTest();
        if (!$connection) {
            self::$notificationList[] = 'Salesforce connection failed';

            return false;
        }

        return true;
    }

    /**
     * convert string with delimiter to camel case
     *
     * @param $string
     * @param string $delimiter
     * @param bool $capitalizeFirstCharacter
     * @return mixed
     */
    private function toCamelCase($string, $delimiter = '_', $capitalizeFirstCharacter = false)
    {
        $str = str_replace(' ', '', ucwords(str_replace($delimiter, ' ', $string)));
        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }

        return $str;
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
        // check if cache has a "sf session id"
        // if cache is disabled, check if "sf session id is" in the magento session
        $this->validateStorage();
        $mageCache = Mage::app()->getCache();
        $useCache = Mage::app()->useCache('tnw_salesforce');
        if ($useCache) {
            $res = unserialize($mageCache->load($key));
        }
        else {
            $keyCamelCase = 'get'.$this->toCamelCase($key, '_', true);
            $res = Mage::getSingleton('core/session')->$keyCamelCase();
        }

        return $res;
    }

    /**
     * check is salesforce session was established more than 90 minutes ago
     * if so - create new session
     */
    public function validateStorage(){
        $mageCache = Mage::app()->getCache();
        $useCache = Mage::app()->useCache('tnw_salesforce');
        if ($useCache) {
            $creationTime = unserialize($mageCache->load('salesforce_session_created'));
        }
        else {
            $keyCamelCase = 'get'.$this->toCamelCase('salesforce_session_created', '_', true);
            $creationTime = Mage::getSingleton('core/session')->$keyCamelCase();
        }
        if ($creationTime){
            $sessionExists = time() - $creationTime;
        }
        if (empty($creationTime) || ($sessionExists > 540) ){
            Mage::getSingleton('tnw_salesforce/connection')->initConnection();
        }

    }

    /**
     * set value to cache or session
     *
     * @param $key
     * @param $value
     * @return bool
     */
    public function setStorage($value, $key)
    {
        $mageCache = Mage::app()->getCache();
        $useCache = Mage::app()->useCache('tnw_salesforce');
        if ($useCache) {
            $res = $mageCache->save(serialize($value), $key, array("TNW_SALESFORCE"));
        }
        else {
            $keyCamelCase = 'set'.$this->toCamelCase($key, '_', true);
            $res = Mage::getSingleton('core/session')->$keyCamelCase($value);
        }

        return $res;
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
            if (!$fromCli && Mage::helper('tnw_salesforce')->displayErrors()) {
                $text = implode("<br />", self::$notificationList);
                Mage::getSingleton('core/session')->addNotification($text);
            }
            else {
                $text = implode("\n", self::$notificationList);
                echo $text;
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
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace($line);
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