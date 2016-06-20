<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/* Instantiate Magento */
$mageFilename = '../app/Mage.php';
require_once $mageFilename;
unset($mageFilename);
$app = Mage::app();
$app->setCurrentStore(0);

$helper = Mage::helper('tnw_salesforce');

// set locale
$locale = $app->getLocale()->getLocaleCode();
$app->getTranslator()->setLocale($locale)->init('frontend', true);

/* Look for passed name/value pair */
$fromSf = ($_REQUEST['sf']) ? strip_tags($_REQUEST['sf'], true) : NULL;
Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("========== Sync from Salesforce Start ==========");
Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("JSON: ". urlencode($fromSf));

if (!function_exists('apache_request_headers')) {
    function apache_request_headers()
    {
        $arh = array();
        $rx_http = '/\AHTTP_/';
        foreach ($_SERVER as $key => $val) {
            if (preg_match($rx_http, $key)) {
                $arh_key = preg_replace($rx_http, '', $key);
                $rx_matches = array();
                // do some nasty string manipulations to restore the original letter case
                // this should work in most cases
                $rx_matches = explode('_', $arh_key);
                if (count($rx_matches) > 0 and strlen($arh_key) > 2) {
                    foreach ($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
                    $arh_key = implode('-', $rx_matches);
                }
                $arh[ucwords(strtolower($arh_key))] = $val;
            }
        }
        return ($arh);
    }
}

$headers = apache_request_headers();
if (
    !array_key_exists('Authorization', $headers)
) {
    if (isset($_SERVER['REDIRECT_REMOTE_USER'])) {
        $headers['Authorization'] = ($_SERVER['REDIRECT_REMOTE_USER']) ? $_SERVER['REDIRECT_REMOTE_USER'] : NULL;
    }
}

/* Setting default values */
$response = new stdClass();

$errorString = NULL;
if (!$helper->isWorking()) {
    $errorString = "Extension is disabled or not working";
} else {
    /* Validate Request */
    try {
        if (!array_key_exists('Authorization', $headers)) {
            throw new Exception("Missing authorization, possible attack!");
        }

        $auth = explode(" ", $headers['Authorization']);
        $myAuth = base64_decode($auth[1], true);
        $credentials = explode(":", $myAuth);
        if (count($auth) != 2 || $auth[0] !== "BASIC" || !$myAuth || !is_array($credentials) || count($credentials) != 2) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Trying to hack, eh?');
            throw new Exception("Authorization invalid, possible attack!");
        }
        // Final check to see if all is good
        if ($helper->getLicenseInvoice() != $credentials[0] || $helper->getLicenseEmail() != $credentials[1]) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: Authentication fialed!');
            throw new Exception("Authorization invalid, possible attack!");
        }
    } catch (Exception $e) {
        Mage::getSingleton('tnw_salesforce/tool_log')->saveError('ERROR: ' . $e->getMessage());

        /* Create JSON to send back to Salesforce */
        $response->error = $e->getMessage();
        $return = json_encode($response);
        print($return);

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Return JSON to Salesforce: " . $return);
        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("========== Sync from Salesforce End ==========");
        exit;
    }

    if ($helper->getType() != "PRO") {
        /* 2 way sync is disabled */
        $errorString = "Please upgrade PowerSync to support two way synchronization";
    } else if (!$fromSf) {
        /* Could not find required variable 'sf' */
        $errorString = "Salesforce object is not available!";
    } else {
        /* Decode JSON into PHP object */
        $objects = json_decode($fromSf);
        if (!$objects || !is_array($objects)) {
            /* JSON is invalid, hack? */
            $errorString = "Invalid JSON format!";
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($errorString);
        } else {
            // Check if integration is enabled in Magento
            if (!$helper->isEnabled()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace('SKIPING: Synchronization is disabled');
            } else {
                if (count($objects) == 1) {
                    // Process Realtime
                    $object = $objects[0];
                    try {
                        /** @var TNW_Salesforce_Model_Import $import */
                        $import = Mage::getModel('tnw_salesforce/import');
                        $_association = $import->setObject($object)
                            ->process();

                        $import->sendMagentoIdToSalesforce($_association);
                    } catch (Exception $e) {
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Error: " . $e->getMessage());
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Failed to upsert a " . $object->attributes->type . " #" . $object->Id . ", please re-save or re-import it manually");
                    }
                } else {
                    // Add to Queue
                    /* Save into a db */
                    try {
                        Mage::getModel('tnw_salesforce/import')
                            ->setObject($fromSf)
                            ->save();
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Import JSON accepted, pending Import");
                    } catch (Exception $e) {
                        $errorString = "Could not process JSON, Error: " . $e->getMessage();
                        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace($errorString);
                    }
                }
            }
        }
    }
}

/* Create JSON to send back to Salesforce */
$response->error = $errorString;
$return = json_encode($response);
print($return);

Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Return JSON to Salesforce: " . $return);
Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("========== Sync from Salesforce End ==========");
