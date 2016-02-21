<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
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
            $this->_requestToResponse = $expected->getData();

            foreach ($this->_requestToResponse as $key => $data) {
                $data['request'] = $this->trimXmlString($data['request']);
                $this->_requestToResponse[$key] = $data;
            }
        }
    }

    /**
     * @comment remove all space symbols for the string comparing
     * @param $xmlString
     * @return mixed
     */
    public function trimXmlString(&$xmlString)
    {
        $xmlString = str_replace(array(' ', "\n", "\r", "\t"), '', $xmlString);

        return $xmlString;
    }

    /**
     * @comment save data for request
     */
    public function setRawData()
    {
        return function ($data, $enctype = null) {
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
            $responseBody = null;

            if (!empty($this->_bulkClientRawData)) {
                $searchBy = 'request';
                $searchSign = $this->_bulkClientRawData;
            } else {
                $searchBy = 'uri';
                $searchSign = $this->_bulkClient->getUri(true);
            }

            foreach ($this->_requestToResponse as $transactionData) {
                if ($transactionData[$searchBy] == $searchSign) {
                    $responseBody = $transactionData['response'];
                }
            }

            $response->setBody($responseBody);

            $this->_bulkClient->setRawData(null);
            return $response;
        };
    }
}