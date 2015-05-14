<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 *
 * Class TNW_Salesforce_Test_Bulkcase
 */
abstract class TNW_Salesforce_Test_Bulkcase extends TNW_Salesforce_Test_Case
{
    /**
     * @var Zend_Http_Client
     */
    protected $_bulkClient = null;

    /**
     * @var null
     */
    protected $_bulkClientRawData = null;

    /**
     * @comment key - request string, value - response string
     * @var array
     */
    protected $_requestToResponse = array();


    /**
     * @comment load request-response pairs from expectation file
     */
    protected function _prepareClientData()
    {
        $expected = $this->expected();
        if (!empty($expected)) {
            foreach ($expected->getData() as $requestType => $data) {
                if (
                    (isset($data['request']) || isset($data['uri']))
                    && isset($data['response'])
                ) {

                    $request = $this->trimXmlString($data['request']);
                    $uri = $data['uri'];
                    $response = $data['response'];

                    $key = $request;
                    if (empty($key)) {
                        $key = $uri;
                    }

                    $this->_requestToResponse[$key] = $response;
                }
            }
        }
    }

    /**
     * @comment remove all space symbols for the string comparing
     * @param $xmlString
     * @return mixed
     */
    public function trimXmlString($xmlString)
    {
        $xmlString = str_replace(array(' ', "\n", "\r", "\t"), '', $xmlString);

        return $xmlString;
    }
    /**
     *
     */
    public function getHttpClient()
    {
        $this->_bulkClient = $this->mockClass('Zend_Http_Client', array('request', 'setRawData'));

        $this->_bulkClient->expects($this->any())
            ->method('setRawData')
            ->will($this->returnCallback($this->setRawData()));

        $this->_bulkClient->expects($this->any())
            ->method('request')
            ->will($this->returnCallback($this->request()));

        return $this->_bulkClient;
    }

    /**
     * @comment save data for request
     */
    public function setRawData()
    {
        return function($data, $enctype = null) {
            $this->_bulkClientRawData = $data;
            $this->_bulkClientRawData = $this->trimXmlString($this->_bulkClientRawData);
            return $this;
        };
    }

    /**
     * @comment emulate request sending
     *
     * @return Varien_Object
     */
    public function request()
    {

        return function () {

            $response = new Varien_Object();

            /**
             * @comment try to find response by request or uri
             */
            $key = $this->_bulkClientRawData;
            if (!isset($this->_requestToResponse[$key])) {
                $key = $this->_bulkClient->getUri(true);
            }

            if (isset($this->_requestToResponse[$key])) {
                $response->setBody($this->_requestToResponse[$key]);
            }

            $this->_bulkClient->setRawData(null);
            return $response;
        };
    }
}