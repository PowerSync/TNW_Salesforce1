<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Api_Client
{
    protected $client;

    protected function getConnection()
    {
        return Mage::getSingleton('tnw_salesforce/connection');
    }

    protected function getClient()
    {
        if (is_null($this->client)) {
            $this->getConnection()->initConnection();
            $this->client = $this->getConnection()->getClient();
        }

        return $this->client;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function convertLead($data)
    {
        $response = $this->getClient()->convertLead($data);

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
        $response = $this->getClient()->query((string)$sql);

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
        $response = $this->getClient()->queryAll((string)$sql, $queryOptions);

        $result = array();
        if (isset($response->records) && !empty($response->records)) {
            foreach ($response->records as $_row) {
                $result[] = (array)$_row;
            }
        }

        return $result;
    }

    public function upsert($id, $data, $entity)
    {
        if (is_array($data) || is_object($data)) {
            //array of arrays
            if (is_array(reset($data))) {
                foreach ($data as &$item) {
                    $object = new stdClass();
                    foreach ($item as $key => $value) {
                        $object->$key = $value;
                    }
                    $item = $object;
                }
                // just one item passed as array or object
            } elseif (!is_object(reset($data))) {
                if (is_object($data)) {
                    $data = array($data);
                } else {
                    $object = new stdClass();
                    foreach ($data as $key => $value) {
                        $object->$key = $value;
                    }
                    $data = array($object);
                }
            }
        }

        //check that param passed correct
        if (!is_array($data) || !is_object(reset($data))) {
            Mage::throwException('Not correct param passed to upsert.');
        }

        //init connection before
        if (!$this->getConnection()->initConnection()) {
            Mage::throwException('Cannot init connection: ' . $this->getConnection()->getLastErrorMessage());
        }

        $clientResult = $this->getClient()->upsert((string)$id, $data, (string)$entity);
        $result = array();
        if (is_array($clientResult)) {
            foreach ($clientResult as $key => $object) {
                $result[$key] = new Varien_Object((array)$object);
            }
        }

        return $result;
    }
}