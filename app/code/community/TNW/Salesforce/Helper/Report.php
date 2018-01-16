<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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
            $_dataToLog->type = $_type;

            $_response = $this->findResponse($_key, $_responses);
            if (null !== $_response) {
                $_dataToLog->response = $_response;
                $_dataToLog->status = is_object($_dataToLog->response)
                    ? $_dataToLog->response->success
                    : ($_dataToLog->response['success'] == "true") ? true : false;
            }
            else {
                $_dataToLog->response = 'unknown key: ' . $_key . ' for type: ' . $_type;
                $_dataToLog->status   = 'unknown';
            }

            $this->_logData[] = $_dataToLog;
        }
    }

    /**
     * @param $_key
     * @param array $_responses
     * @return stdClass|null
     */
    protected function findResponse($_key, $_responses)
    {
        if (array_key_exists($_key, $_responses)) {
            return $_responses[$_key];
        }

        foreach ($_responses as $response) {
            if (!isset($response['subObj'][$_key])) {
                continue;
            }

            return $response['subObj'][$_key];
        }

        return null;
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
                Mage::helper('tnw_salesforce')->getLicenseInvoice() . $this->_separator .
                Mage::helper('tnw_salesforce')->getLicenseEmail() . $this->_separator .
                $this->_serverName . $this->_separator .
                json_encode($this->_logData) . $this->_separator .
                'PRO' . $this->_separator .
                Mage::helper('tnw_salesforce')->getExtensionVersion()
            ;

            $client->setParameterPost('log', $validator);
            unset($validator);

            $client->setHeaders('Host', $this->_serverName);
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