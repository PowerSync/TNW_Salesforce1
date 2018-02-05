<?php

class TNW_Salesforce_Model_Sforce_Client extends Salesforce_SforceEnterpriseClient
{


    /**
     * Connect method to www.salesforce.com
     *
     * @param string $wsdl Salesforce.com Partner WSDL
     * @param stdClass $proxy
     * @return TNW_Salesforce_Model_Sforce_Soapclient
     */
    public function createConnection($wsdl, $proxy = null)
    {
        $soapClientArray = array(
            'user_agent' => 'salesforce-toolkit-php/' . $this->version,
            'encoding' => 'utf-8',
            'trace' => 1,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP
        );

        // We don't need to parse out any subversion suffix - e.g. "-01" since
        // PHP type conversion will ignore it
        if (phpversion() < 5.2) {
            throw new Exception("PHP versions older than 5.2 are no longer supported. Please upgrade!");
        }

        if ($proxy != null) {
            $proxySettings = array();
            $proxySettings['proxy_host'] = $proxy->host;
            $proxySettings['proxy_port'] = $proxy->port; // Use an integer, not a string
            $proxySettings['proxy_login'] = $proxy->login;
            $proxySettings['proxy_password'] = $proxy->password;
            $soapClientArray = array_merge($soapClientArray, $proxySettings);
        }

        $this->sforce = new TNW_Salesforce_Model_Sforce_Soapclient($wsdl, $soapClientArray);

        return $this->sforce;
    }

    /**
     * @param string $ext_Id
     * @param array $sObjects
     * @param string $type
     * @return stdClass
     */
    public function upsert($ext_Id, $sObjects, $type = 'Contact')
    {
        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("UPSERT: %s (%s) \n%s", $type, $ext_Id, print_r($sObjects, true)));

        $return = parent::upsert($ext_Id, $sObjects, $type);

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace(sprintf("UPSERT Result: %s (%s) \n%s", $type, $ext_Id, print_r($return, true)));
        return $return;
    }

    /**
     * @param string $sql
     *
     * @return stdClass
     */
    public function query($sql)
    {
        $response = parent::query((string)$sql);

        $result = array();
        if (isset($response->records) && !empty($response->records)) {
            foreach ($response->records as $_row) {
                $result[] = $_row;
            }
        }

        while (!$response->done) {
            $response = $this->queryMore($response->queryLocator);
            if (isset($response->records) && !empty($response->records)) {
                foreach ($response->records as $_row) {
                    $result[] = $_row;
                }
            }
        }

        $return = (object)array(
            'done' => true,
            'queryLocator' => null,
            'size' => count($result),
            'records' => $result
            );

        return $return;
    }
}
