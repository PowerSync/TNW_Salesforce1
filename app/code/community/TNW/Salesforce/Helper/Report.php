<?php

/**
 * Class TNW_Salesforce_Helper_Report
 */
class TNW_Salesforce_Helper_Report extends TNW_Salesforce_Helper_Abstract
{
    /**
     * @var string
     */
    protected $_key = "If you hack this I kill you!";

    /**
     * @var string
     */
    protected $_separator = ':@:';

    /**
     * @var array
     */
    protected $_logData = array();

    /**
     * @var null
     */
    protected $_serverName = NULL;

    /**
     * @var null
     */
    protected $_magentoId = NULL;


    public function add($_target = 'Salesforce', $_type = 'Product2', $_records = array(), $_responses = array()) {
        foreach ($_records as $_key => $_record) {
            $_dataToLog = new stdClass();
            $_dataToLog->data = $_record;
            $_dataToLog->id = $_key;
            $_dataToLog->targetSystem = $_target;
            if (!array_key_exists($_key, $_responses)) {
                $_dataToLog->status = 'unknown';
                $_responses[$_key] = 'unknown key: ' . $_key . ' for type: ' . $_type;
            } else if (is_object($_responses[$_key])) {
                $_dataToLog->status = ($_responses[$_key]->success) ? true : false;
            } else {
                $_dataToLog->status = ($_responses[$_key]['success'] == "true") ? true : false;
            }
            $_dataToLog->type = $_type;
            $_dataToLog->response = $_responses[$_key];
            $this->_logData[] = serialize($_dataToLog);
        }
    }

    /**
     * @return bool
     */
    public function send() {
        if (count($this->_logData) == 0) {
            return true;
        }
        if (!$this->_serverName) {
            $_urlArray = explode('/', Mage::app()->getStore(Mage::helper('tnw_salesforce')->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB));
            $this->_serverName = (array_key_exists('2', $_urlArray)) ? $_urlArray[2] : NULL;
            if (!$this->_serverName) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: Cannot extract SERVER_NAME from PHP!");
                return false;
            }
        }

        try {
            $client = new Zend_Http_Client('http://www.idealdata.io/monitoring/log');
            $validator =
                $this->_encrypt(Mage::helper('tnw_salesforce')->getLicenseInvoice()) . $this->_separator .
                $this->_encrypt(Mage::helper('tnw_salesforce')->getLicenseEmail()) . $this->_separator .
                $this->_encrypt($this->_serverName) . $this->_separator .
                $this->_encrypt(serialize($this->_logData))
            ;

            $client->setParameterPost('log', $validator);
            unset($validator);

            $client->setMethod(Zend_Http_Client::POST);
            @$client->request()->getBody();
        } catch(Exception $e) {
            //TODO: log
            return false;
        }
        return true;
    }

    public function reset() {
        $this->_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";
    }

    /**
     * @param string $string
     * @return string
     */
    protected function _decrypt($string = "")
    {
        return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($this->_key), base64_decode($string), MCRYPT_MODE_CBC, md5(md5($this->_key))), "\0");
    }

    /**
     * @param string $string
     * @return string
     */
    protected function _encrypt($string = "")
    {
        return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($this->_key), $string, MCRYPT_MODE_CBC, md5(md5($this->_key))));
    }
}