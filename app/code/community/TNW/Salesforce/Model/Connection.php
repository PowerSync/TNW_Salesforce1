<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Connection extends Mage_Core_Model_Session_Abstract
{
    /**
     * @var Salesforce_SforceEnterpriseClient
     */
    protected $_client = NULL;

    /**
     * @var null
     */
    protected $_wsdl = NULL;

    /**
     * @var bool|SoapClient
     */
    protected $_connection = FALSE;

    /**
     * @var bool
     */
    protected $_loggedIn = FALSE;

    /**
     * @var bool
     */
    protected $_userAgent = FALSE;

    /**
     * @var null
     */
    protected $_sfPackagePrefix = NULL;

    /**
     * @var null
     */
    protected $_errorMessage = NULL;

    /**
     * @var null
     */
    protected $_sessionId = NULL;

    /**
     * @var null
     */
    protected $_serverUrl = NULL;

    public function clearMemory()
    {
        set_time_limit(1000);
        gc_enable();
        gc_collect_cycles();
        gc_disable();
    }

    public function __construct()
    {
        $this->_errorMessage = NULL;
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            $system = array(
                'name' => 'unrecognized',
                'version' => 'unknown',
                'platform' => 'unrecognized',
                'userAgent' => ''
            );
            // Array was causing issues with Redis Cache, this variable has to be a string
            $_SERVER['HTTP_USER_AGENT'] = join(' ', $system);
        }
        $this->_userAgent = $_SERVER['HTTP_USER_AGENT'];
        # Disable SOAP cache
        ini_set('soap.wsdl_cache_enabled', 0);
        if (
            !$this->_client &&
            Mage::helper('tnw_salesforce')->isWorking()
        ) {
            # instantiate a new Salesforce object
            $this->_client = new Salesforce_SforceEnterpriseClient();
        } else {
            Mage::getSingleton('adminhtml/session')->addNotice("Salesforce API connectivity issue, sync is disabled. Check API configuration and try manual synchronization.");
        }
    }

    /**
     * @return bool
     */
    public function tryWsdl()
    {
        if (defined('MAGENTO_ROOT')) {
            $basepath = MAGENTO_ROOT;
        } else if (defined('BP')) {
            $extra = "";
            if (Mage::helper('tnw_salesforce')->getMagentoVersion() < 1500) {
                $extra = "/../";
            }
            $basepath = realpath(BP . $extra);
        } else {
            $basepath = realpath(dirname(__FILE__) . "/../../../../../../");
        }

        $this->_wsdl = $basepath . "/" . Mage::helper('tnw_salesforce')->getApiWSDL();
        if (!file_exists($this->_wsdl) || Mage::helper('tnw_salesforce')->getApiWSDL() == "") {
            $this->_wsdl = NULL;
            Mage::helper('tnw_salesforce')->log("WSDL file not found!");
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function tryToConnect()
    {
        if (!$this->isWsdlFound() || !is_object($this->_client)) {
            return false;
        }
        if (!$this->_connection) {
            try {
                $this->_errorMessage = NULL;
                # instantiate a SOAP connection to Salesforce
                $this->_connection = $this->_client->createConnection($this->_wsdl);
            } catch (Exception $e) {
                $this->_errorMessage = $e->getMessage();
                Mage::helper('tnw_salesforce')->log('WARNING: ' . $e->getMessage());
                return false;
            }
            $_SERVER['HTTP_USER_AGENT'] = $this->_userAgent;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function tryToLogin()
    {
        $success = true;
        if (!is_object($this->_client)) {
            return false;
        }

        if (!is_object($this->_loggedIn)) {
            try {
                $this->_errorMessage = NULL;
                $user = Mage::helper('tnw_salesforce')->getApiUsername();
                $pass = Mage::helper('tnw_salesforce')->getApiPassword();
                $token = Mage::helper('tnw_salesforce')->getApiToken();

                // log in to salesforce
                $this->_loggedIn = $this->_client->login($user, $pass . $token);

                if (property_exists($this->_loggedIn, 'sessionId')) {
                    $this->_sessionId = $this->_loggedIn->sessionId;
                    Mage::getSingleton('core/session')->setSalesforceSessionId($this->_loggedIn->sessionId);
                    Mage::getSingleton('core/session')->setSalesforceSessionCreated(time());
                    Mage::helper('tnw_salesforce/test_authentication')->setStorage($this->_sessionId, 'salesforce_session_id');
                    Mage::helper('tnw_salesforce/test_authentication')->setStorage(time(), 'salesforce_session_created');
                }
                if (property_exists($this->_loggedIn, 'serverUrl')) {
                    $this->_serverUrl = $this->_loggedIn->serverUrl;
                    Mage::getSingleton('core/session')->setSalesforceServerUrl($this->_loggedIn->serverUrl);
                    Mage::helper('tnw_salesforce/test_authentication')->setStorage($this->_serverUrl, 'salesforce_server_url');

                    $instance_url = explode('/', $this->_serverUrl);

                    $_salesforceServerDomain = 'https://' . $instance_url[2];
                    Mage::getSingleton('core/session')->setSalesforceUrl($_salesforceServerDomain);
                    Mage::helper('tnw_salesforce/test_authentication')->setStorage($_salesforceServerDomain, 'salesforce_url');

                    $cache = Mage::app()->getCache();
                    if (Mage::app()->useCache('tnw_salesforce')) {
                        $cache->save(serialize($_salesforceServerDomain), "tnw_salesforce_salesforce_url", array("TNW_SALESFORCE"));

                        $cache->save($this->_loggedIn->userInfo->organizationId, "tnw_salesforce_org", array("TNW_SALESFORCE"));
                    }
                    Mage::helper('tnw_salesforce/test_authentication')->setStorage($this->_loggedIn->userInfo->organizationId, 'salesforce_org_id');
                    Mage::getSingleton('core/session')->setSalesForceOrg($this->_loggedIn->userInfo->organizationId);

                    Mage::getSingleton('core/session')->setSfNotWorking(false);
                }
                unset($user, $pass, $token);
            } catch (Exception $e) {
                $this->_errorMessage = $e->getMessage();
                Mage::helper('tnw_salesforce')->log("Login Failure: " . $e->getMessage());
                unset($e);
                return false;
            }

            $success = $this->checkPackage();
        }

        return $success;
    }

    /**
     * @comment check if our package is installed in Salesforce
     * @return bool
     */
    public function checkPackage()
    {
        try {
            /**
             * @comment try to take object from our package
             */
            $salesforceWebsiteDescr = $this
                ->getClient()
                ->describeSObject(
                    Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()
                );

        } catch (Exception $e) {
            $this->_loggedIn = null;

            $errorMessage = Mage::helper('tnw_salesforce')->__('Cannot find PowerSync package in you Salesforce');

            $this->_errorMessage = $errorMessage;
            Mage::helper('tnw_salesforce')->log("checkPackage Failure: " . $errorMessage);
            Mage::helper('tnw_salesforce')->log("checkPackage Failure, Error details: " . $e->getMessage());
            unset($e);
            return false;
        }

        return true;
    }

    public function getLoginResponse()
    {
        return $this->_loggedIn;
    }

    /**
     * init connection with sf
     *
     * @return bool|null
     */
    public function initConnection()
    {
        try {
            $this->_errorMessage = null;
            if (
                $this->isConnected()
                && $this->tryToLogin()
            ) {
                return $this->_client;
            }
        } catch (Exception $e) {
            $this->_errorMessage = $e->getMessage();
            Mage::helper('tnw_salesforce')->log("Connection Failure: " . $e->getMessage());
        }

        return false;
    }

    /**
     * @return Salesforce_SforceEnterpriseClient
     */
    public function getClient()
    {
        return ($this->isConnected()) ? $this->_client : $this->initConnection();
    }

    public function isWsdlFound()
    {
        return $this->_wsdl;
    }

    public function isConnected()
    {
        if (!$this->_connection && $this->tryWsdl()) {
            $this->tryToConnect();
        }
        return $this->_connection;
    }

    public function isLoggedIn()
    {
        if (!$this->_loggedIn) {
            if ($this->isConnected()) {
                $this->tryToLogin();
            }
        }
        return $this->_loggedIn;
    }

    public function getLastErrorMessage()
    {
        return $this->_errorMessage;
    }

    public function getSessionId()
    {
        return $this->_sessionId;
    }

    public function getServerUrl()
    {
        return $this->_serverUrl;
    }
}