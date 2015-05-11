<?php

class TNW_Salesforce_Model_Api_Client
{
    protected $_client;

    protected function _getClient()
    {
        if (is_null($this->_client)) {
            $this->_client = Mage::getSingleton('tnw_salesforce/connection')->getClient();
        }

        return $this->_client;
    }



    /**
     * @param array $data
     *
     * @return array
     */
    public function convertLead($data)
    {
        $response = $this->_getClient()->convertLead($data);

        $result = array();
        if (isset($response->result) && !empty($response->result)) {
            foreach ($response->result as $_row) {
                $result[] = (array)$_row;
            }
        }
        return $result;
    }

    /**
     * @param string $sql
     *
     * @return array
     */
    public function query($sql)
    {
        $response = $this->_getClient()->query((string)$sql);

        $result = array();
        if (isset($response->records) && !empty($response->records)) {
            foreach ($response->records as $_row) {
                $result[] = (array)$_row;
            }
        }

        return $result;
    }

    /**
     * @param string $sql
     *
     * @return array
     */
    public function queryAll($sql, $queryOptions = NULL)
    {
        $response = $this->_getClient()->queryAll((string)$sql, $queryOptions);

        $result = array();
        if (isset($response->records) && !empty($response->records)) {
            foreach ($response->records as $_row) {
                $result[] = (array)$_row;
            }
        }

        return $result;
    }
}