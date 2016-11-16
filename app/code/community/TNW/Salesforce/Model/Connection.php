<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Connection extends Mage_Core_Model_Session_Abstract
{
    /**
     * seconds
     */
    const CONNECTION_TIME_LIMIT = 60;

    /**
     * @var TNW_Salesforce_Model_Sforce_Client
     */
    protected $_client = NULL;

    /**
     * @var
     */
    protected $_packageAvailable = NULL;

    /**
     * @var
     */
    protected $_packageShipmentAvailable = NULL;

    /**
     * @var
     */
    protected $_packageInvoiceAvailable = NULL;

    /**
     * @var null
     */
    protected $_wsdl = NULL;

    /**
     * @var bool|SoapClient
     */
    protected $_connection = FALSE;

    /**
     * @var stdClass
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
        if (!$this->_client) {
            # instantiate a new Salesforce object
            $this->_client = new TNW_Salesforce_Model_Sforce_Client();
        } else {
            Mage::getSingleton('adminhtml/session')->addNotice("Salesforce API connectivity issue, sync is disabled. Check API configuration and try manual synchronization.");
        }
    }

    /**
     * @return bool|null|string
     */
    public function getWsdl()
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
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("WSDL file not found!");
        }

        return $this->_wsdl;
    }

    /**
     * @return bool
     */
    public function tryWsdl()
    {

        if (!$this->getWsdl()) {
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
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError('WARNING: ' . $e->getMessage());
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
        if (!is_object($this->_connection)) {
            return false;
        }

        if (is_object($this->_loggedIn)) {
            return true;
        }

        try {
            $this->_errorMessage = NULL;
            $user  = Mage::helper('tnw_salesforce')->getApiUsername();
            $pass  = Mage::helper('tnw_salesforce')->getApiPassword();
            $token = Mage::helper('tnw_salesforce')->getApiToken();

            // log in to salesforce
            $this->_loggedIn = $this->_client->login($user, $pass . $token);

            if (property_exists($this->_loggedIn, 'sessionId')) {
                $this->_sessionId = $this->_loggedIn->sessionId;
                Mage::getSingleton('core/session')
                    ->addData(array(
                        'salesforce_session_id'      => $this->_loggedIn->sessionId,
                        'salesforce_session_created' => time()
                    ));
            }

            if (property_exists($this->_loggedIn, 'serverUrl')) {
                $this->_serverUrl = $this->_loggedIn->serverUrl;
                Mage::helper('tnw_salesforce/test_authentication')
                    ->setStorage($this->_serverUrl, 'salesforce_server_url');

                $instance_url = explode('/', $this->_serverUrl);
                $_salesforceServerDomain = 'https://' . $instance_url[2];
                Mage::helper('tnw_salesforce/test_authentication')
                    ->setStorage($_salesforceServerDomain, 'salesforce_url');

                Mage::helper('tnw_salesforce/test_authentication')
                    ->setStorage($this->_loggedIn->userInfo->organizationId, 'salesforce_org_id');

                Mage::getSingleton('core/session')->setSfNotWorking(false);
            }

        } catch (Exception $e) {
            $this->_errorMessage = $e->getMessage();
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("Login Failure: " . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @comment check if our package is installed in Salesforce
     * @return bool
     */
    public function checkPackage()
    {
        
        if (is_null($this->_packageAvailable)) {

            try {

                /**
                 * connection not exists or authorization failed
                 */
                if (!$this->_connection || !$this->_loggedIn) {
                    $this->_packageAvailable = false;
                    return $this->_packageAvailable;
                }

                /**
                 * @comment try to take object from our package
                 */
                $this->_client->describeSObject(
                    Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . Mage::helper('tnw_salesforce/config_website')->getSalesforceObject()
                );

                $this->_packageAvailable = true;
                TNW_Salesforce_Helper_Test_License::validateDateUpdate();
            }
            catch (Exception $e) {
                $this->_loggedIn = null;
                $this->_connection = null;

                $errorMessage = $e->getMessage();
                if ($e instanceof SoapFault && $e->faultcode == 'sf:INVALID_TYPE') {
                    $errorMessage = Mage::helper('tnw_salesforce')->__('PowerSync managed package in Salesforce is either not installed or license is expired.<br />');
                    $errorMessage .= Mage::helper('tnw_salesforce')->__('Please contact <a href="https://technweb.atlassian.net/servicedesk/customer/portal/2">Powersync Support</a> for more information');
                }

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError($errorMessage);

                $this->_errorMessage = $errorMessage;
                $this->_packageAvailable = false;
                TNW_Salesforce_Helper_Test_License::validateDateReset();
            }
        }

        return $this->_packageAvailable;
    }

    /**
     * @comment check if our package is installed in Salesforce
     * @return bool
     */
    public function checkShipmentPackage()
    {
        if (!is_null($this->_packageShipmentAvailable)) {
            return $this->_packageShipmentAvailable;
        }

        /**
         * connection not exists or authorization failed
         */
        if (!$this->_connection || !$this->_loggedIn) {
            $this->_packageShipmentAvailable = false;
            return $this->_packageShipmentAvailable;
        }

        try {
            /** @comment try to take object from our package */
            $this->_client->describeSObject(TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_OBJECT);
            $this->_packageShipmentAvailable = true;
        }
        catch (Exception $e) {
            $errorMessage = Mage::helper('tnw_salesforce')->__('PowerSync Shipment managed package in Salesforce is either not installed or license is expired.<br />');
            $errorMessage .= Mage::helper('tnw_salesforce')->__('Please contact <a href="https://technweb.atlassian.net/servicedesk/customer/portal/2">Powersync Support</a> for more information');
            $this->_errorMessage = $errorMessage;

            Mage::getSingleton('tnw_salesforce/tool_log')->saveError($errorMessage);
            $this->_packageShipmentAvailable = false;
        }

        return $this->_packageShipmentAvailable;
    }

    /**
     * @comment check if our package is installed in Salesforce
     * @return bool
     */
    public function checkInvoicePackage()
    {
        if (!is_null($this->_packageInvoiceAvailable)) {
            return $this->_packageInvoiceAvailable;
        }

        /**
         * connection not exists or authorization failed
         */
        if (!$this->_connection || !$this->_loggedIn) {
            $this->_packageInvoiceAvailable = false;
            return $this->_packageInvoiceAvailable;
        }

        try {
            /** @comment try to take object from our package */
            $this->_client->describeSObject(TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT);
            $this->_packageInvoiceAvailable = true;
        }
        catch (Exception $e) {
            $errorMessage = Mage::helper('tnw_salesforce')->__('PowerSync Invoice managed package in Salesforce is either not installed or license is expired.<br />');
            $errorMessage .= Mage::helper('tnw_salesforce')->__('Please contact <a href="https://technweb.atlassian.net/servicedesk/customer/portal/2">Powersync Support</a> for more information');
            $this->_errorMessage = $errorMessage;

            Mage::getSingleton('tnw_salesforce/tool_log')->saveError($errorMessage);
            $this->_packageInvoiceAvailable = false;
        }

        return $this->_packageInvoiceAvailable;
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
            if ($this->isConnected()) {
                return $this->_client;
            }
        } catch (Exception $e) {
            $this->_errorMessage = $e->getMessage();
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Connection Failure: " . $e->getMessage());
        }

        return false;
    }

    /**
     * @return Salesforce_SforceEnterpriseClient
     */
    public function getClient()
    {
        $this->getConnection();
        return $this->_client;
    }

    public function isWsdlFound()
    {
        return $this->_wsdl;
    }

    /**
     * @return null|SoapClient
     */
    public function isConnected()
    {
        try {
            $this->getConnection();
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Connection Failure: " . $e->getMessage());
        }

        return $this->_connection;
    }

    /**
     * @return null|SoapClient
     * @throws Exception
     */
    public function getConnection()
    {
        $currentTime = time();
        if ($currentTime - (int)$this->getPreviousTime() > self::CONNECTION_TIME_LIMIT) {
            $this->setPreviousTime($currentTime);
            $this->_connection = null;
            $this->_loggedIn = null;

            if (!$this->tryWsdl()) {
                throw new Exception('The "Wsdl" test failed');
            }

            if (!$this->tryToConnect()) {
                throw new Exception('The "Connection" test failed');
            }

            if (!$this->tryToLogin()) {
                throw new Exception('The "Login" test failed');
            }

            if (TNW_Salesforce_Helper_Test_License::isValidate() && !$this->checkPackage()) {
                throw new Exception('The "License" test failed');
            }
        }

        return $this->_connection;
    }

    public function isLoggedIn()
    {
        if (!$this->_loggedIn) {
            /**
             * the "isConnected" already contain log-in action
             */
            $this->isConnected();
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