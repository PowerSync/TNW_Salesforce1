<?php

/**
 * Class TNW_Salesforce_Model_License
 */
class TNW_Salesforce_Model_License
{
    const KEY = "If you hack this I kill you!";
    const FREEZE_DURATION = 7200; // 2 hours we do not disturb server if it failed
    const TEST_INTERVAL = 172800;   // 2 days, check license every 2 days
    const SEPARATOR = ':@:';
    const TITLE = 'Powersync&#153; license validation';

    /**
     * @var null
     */
    protected $_serverName = NULL;

    /**
     * @var null
     */
    protected $_mageCache = NULL;

    /**
     * @var bool
     */
    protected $_useCache = false;

    /**
     * licese server url list
     * be aware that license calls are in the same order that we have in array (first array element is called first)
     *
     * @var array
     */
    private $_licenseServerUrlList = array(
        //'http://license/license/gateway' => 0,
        'http://www.idealdata.io/license/gateway' => 0,
        'http://www.powersync.biz/powersync/check.php' => 0, // last array element always should be master server
    );

    /**
     * in logs we cannot show real server urls to clients thus added aliases for log info
     *
     * @var array
     */
    private $_licenseServerUrlListAlias = array(
        //'http://license/license/gateway' => 'server0',
        'http://www.idealdata.io/license/gateway' => 'server1',
        'http://www.powersync.biz/powersync/check.php' => 'server9',
    );

    /**
     * Do I need to validate my license?
     * @var bool
     */
    private $_status = FALSE;

    public function getStatus() {

        if (!$this->_status || ($this->_getLastChecked() + self::TEST_INTERVAL) < time()) {
            $this->_testLicense();
        }
        return $this->_status;
    }

    /**
     * @return bool
     */
    protected function _testLicense()
    {
        $this->_initCache();

        try {
            if ($this->_useCache && $this->_mageCache->load("admin_tnw_powersync_status")) {
                $_cachedStatus = explode(":-:", $this->_decrypt(unserialize($this->_mageCache->load("admin_tnw_powersync_status"))));
                if (
                    array_key_exists(1, $_cachedStatus)
                    && (int)$this->_decrypt($_cachedStatus[1]) == 77
                ) {
                    $this->_status = true;
                }
            } elseif (Mage::getSingleton('core/session')->getAdminTnwPowersyncStatus()) {
                $_cachedStatus = explode(":-:", $this->_decrypt(unserialize(Mage::getSingleton('core/session')->getAdminTnwPowersyncStatus())));
                if (
                    array_key_exists(1, $_cachedStatus)
                    && (int)$this->_decrypt($_cachedStatus[1]) == 77
                ) {
                    $this->_status = true;
                }
            }
        } catch (Exception $e) {
            // Do nothing
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
        }

        // if status false or cache expired - we make new call to server
        if (!$this->_status || ($this->_getLastChecked() + self::TEST_INTERVAL) < time()) {
            $this->_isValid();
        }

        if (!$this->_status) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Invalid license!");
        }
    }

    /**
     * first we check slave mongo server than master powersync.biz
     *
     * @return bool
     */
    protected function _isValid()
    {
        if (!$this->_serverName) {
            $_urlArray = explode('/', Mage::app()->getStore(Mage::helper('tnw_salesforce')->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB));
            $this->_serverName = (array_key_exists('2', $_urlArray)) ? $_urlArray[2] : NULL;
            if (!$this->_serverName) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Cannot extract SERVER_NAME from PHP!");
            }
        }

        // start iterate server list to check the license
        $serverPosition = 0;
        foreach ($this->_licenseServerUrlList as $serverUrl => $timestamp) {
            $serverPosition++;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("License validation: server " . $this->_licenseServerUrlListAlias[$serverUrl] . " iteration started");

            // check server freeze time, if server not answering we'll call it later
            if (intval($timestamp) > 0 && time() - $timestamp < self::FREEZE_DURATION) {
                $_freezePeriod = self::FREEZE_DURATION - (time() - $timestamp);
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("License validation: server " . $this->_licenseServerUrlListAlias[$serverUrl] . " skipped because it's frozen for the next $_freezePeriod seconds");
                continue;
            }

            try {
                $client = new Zend_Http_Client($serverUrl);
                $validator = $this->_encrypt(Mage::helper('tnw_salesforce')->getLicenseInvoice()) . self::SEPARATOR .
                    $this->_encrypt(Mage::helper('tnw_salesforce')->getLicenseEmail()) . self::SEPARATOR .
                    $this->_encrypt($this->_serverName) . self::SEPARATOR .
                    $this->_encrypt(Mage::helper('tnw_salesforce')->getExtensionVersion())
                ;

                $client->setParameterGet('pmts', $validator);
                unset($validator);

                $client->setMethod(Zend_Http_Client::GET);
                $response = json_decode($client->request()->getBody());

                // Update Last checked
                $this->_setLastChecked();

                if (
                    $response
                    && property_exists($response, "status")
                    && $response->status
                    && property_exists($response, "message")
                ) {
                    $msg = explode(self::SEPARATOR, $this->_decrypt($response->message));
                    $email = $this->_decrypt($msg[0]);
                    $transaction = $this->_decrypt($msg[1]);
                    $type = $msg[2];
                    $url = $this->_decrypt($msg[3]);

                    unset($response, $ch, $output, $msg);
                    if (
                        Mage::helper('tnw_salesforce')->getLicenseEmail() == $email
                        && Mage::helper('tnw_salesforce')->getLicenseInvoice() == $transaction
                        && $this->_serverName == $url
                        && strtolower(Mage::helper('tnw_salesforce')->getType()) == $type
                    ) {
                        // license validation ok
                        // set cache with code 77 for sometime - that means license ok
                        $codeValue = serialize($this->_encrypt(self::TITLE . ":-:" . $this->_encrypt(77) . ":-:" . self::TITLE));
                        $this->_status = true;
                    }
                    else {
                        // set cache with code 100 forever - that means license invalid
                        $codeValue = serialize($this->_encrypt(self::TITLE . ":-:" . $this->_encrypt(100) . ":-:" . self::TITLE));
                    }

                    unset($email, $transaction, $url, $type);
                    // Save into cache or session
                    if ($this->_useCache) {
                        $this->_mageCache->save($codeValue, "admin_tnw_powersync_status", array("TNW_SALESFORCE"));
                    } else {
                        // update to use Session if cache is disabled
                        Mage::getSingleton('core/session')->setAdminTnwPowersyncStatus($codeValue);
                    }
                } else {
                    unset($response, $ch, $output, $url);
                }
                if ($this->_status) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("License validation: passed");
                    break;
                }
                // if we current false and have more servers in list - try with other server
                if (!$this->_status && $serverPosition < count($this->_licenseServerUrlList)) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("License validation issue: freezing server " . $this->_licenseServerUrlListAlias[$serverUrl]);
                    // update server freeze time
                    $this->_licenseServerUrlList[$serverUrl] = time();
                }
            } catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("License validation failed. Exception: " . $e->getMessage());
                // update server freeze time
                $this->_licenseServerUrlList[$serverUrl] = time();
            }
        }
    }

    /**
     * @param $_server
     * @return bool
     */
    public function forceTest($_server)
    {
        $this->_serverName = $_server;

        return $this->_isValid();
    }

    protected function _decrypt($string = "")
    {
        return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5(self::KEY), base64_decode($string), MCRYPT_MODE_CBC, md5(md5(self::KEY))), "\0");
    }

    protected function _encrypt($string = "")
    {
        return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5(self::KEY), $string, MCRYPT_MODE_CBC, md5(md5(self::KEY))));
    }

    protected function _getLastChecked()
    {
        if ($this->_useCache) {
            return unserialize($this->_mageCache->load('admin_notifications_lastcheck'));
        } else {
            return unserialize(Mage::getSingleton('core/session')->getTnwLastCheck());
        }
    }

    protected function _setLastChecked()
    {
        if ($this->_useCache) {
            $this->_mageCache->save(serialize(time()), "admin_notifications_lastcheck", array("TNW_SALESFORCE"));
        } else {
            Mage::getSingleton('core/session')->setTnwLastCheck(serialize(time()));
        }
    }

    public function _initCache()
    {
        $this->_mageCache = Mage::app()->getCache();
        $this->_useCache = Mage::app()->useCache('tnw_salesforce');
    }
}