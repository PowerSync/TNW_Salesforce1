<?php

/**
 * Class TNW_Salesforce_Helper_Test_Abstract
 */
abstract class TNW_Salesforce_Helper_Test_Abstract extends TNW_Salesforce_Helper_Abstract
{
    /**
     * @var null
     */
    protected $_title = NULL;

    /**
     * @var null
     */
    protected $_message = NULL;

    /**
     * @var null
     */
    protected $_redirect = NULL;

    /**
     * @var string
     */
    protected $_errorClass = 'error-msg';

    /**
     * @var string
     */
    protected $_successClass = 'success-msg';

    const _error_unknown = 'An error was reported during this test. Please enable logging and check /var/log/salesforce.log';
    const _error_cannot_test = ''; //'This test cannot be performed until all previous tests are successfull';

    /**
     * @return mixed
     */
    abstract protected function _performTest();

    /**
     * @return Varien_Object
     */
    public function performTest()
    {
        try {
            if ($this->_performTest()) {
                return $this->_createResultObject($this->_title, 'Success!', $this->_successClass, NULL);
            }
            return $this->_createResultObject($this->_title, $this->_message, $this->_errorClass, $this->_redirect);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($e->getMessage());

            return $this->_createResultObject($this->_title, $e->getMessage(), $this->_errorClass, $this->_redirect);
        }
    }

    /**
     * @param $title
     * @param $response
     * @param $resultClass
     * @param $redirect
     * @return Varien_Object
     */
    protected function _createResultObject($title, $response, $resultClass, $redirect)
    {
        return new Varien_Object(
            array(
                'title' => $title,
                'response' => $response,
                'result' => $resultClass,
                'redirect' => $redirect
            )
        );
    }
}