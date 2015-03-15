<?php

class TNW_Salesforce_Model_Connection extends Mage_Core_Model_Session_Abstract
{
    /**
     * @var null
     */
    protected $_client = NULL;

    /**
     * @var null
     */
    protected $_wsdl = NULL;

    /**
     * @var bool
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
            $_SERVER['HTTP_USER_AGENT'] = array(
                'name' => 'unrecognized',
                'version' => 'unknown',
                'platform' => 'unrecognized',
                'userAgent' => ''
            );
        }
        $this->_userAgent = $_SERVER['HTTP_USER_AGENT'];
        # Disable SOAP cache
        ini_set('soap.wsdl_cache_enabled', 0);
        if (
            !$this->_client &&
            Mage::helper('tnw_salesforce')->isWorking() &&
            $clientType = Mage::helper('tnw_salesforce')->getApiType()
        ) {
            $cstClass = 'Salesforce_Sforce' . $clientType . 'Client';
            # instantiate a new Salesforce object
            $this->_client = new $cstClass();
            unset($cstClass);
        } else {
            Mage::getSingleton('adminhtml/session')->addNotice("Salesforce API connectivity issue, sync is disabled. Check API configuration and try manual synchronization.");
            // Redirect removed
            #Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section'=>'salesforce')));
            #Mage::app()->getResponse()->sendResponse();
            return;
        }
    }

    /**
     * @param bool $config
     * @return bool
     */
    public function tryWsdl($config = FALSE)
    {
        $basepath = realpath(dirname(__FILE__) . "/../../../../../../");
        if (defined('MAGENTO_ROOT')) {
            $basepath = MAGENTO_ROOT;
        } else if (defined('BP')) {
            $extra = "";
            if (Mage::helper('tnw_salesforce')->getMagentoVersion() < 1500) {
                $extra = "/../";
            }
            $basepath = realpath(BP . $extra);
        }

        $this->_wsdl = $basepath . "/" . Mage::helper('tnw_salesforce')->getApiWSDL();
        unset($basepath);
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
            /*
        } else {
            return false;
            */
        }

        return true;
    }

    /**
     * @return bool
     */
    public function tryToLogin()
    {
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
                //Zend_Registry::set('salesforceClient', $this->_client);
                return $this->_client;
            }
        } catch (Exception $e) {
            $this->_errorMessage = $e->getMessage();
            Mage::helper('tnw_salesforce')->log("Login Failure: " . $e->getMessage());
        }

        return false;
    }

    public function getClient()
    {
        //return (Zend_Registry::isRegistered('salesforceClient')) ? Zend_Registry::get('salesforceClient') : $this->initConnection();
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