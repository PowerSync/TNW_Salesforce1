<?php
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
$helper->log("========== Sync from Salesforce Start ==========");
//$helper->log("JSON: ". urlencode($fromSf));

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

/* Set Default */
$status = false;
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
            $helper->log('ERROR: Trying to hack, eh?');
            throw new Exception("Authorization invalid, possible attack!");
        }
        // Final check to see if all is good
        if ($helper->getLicenseInvoice() != $credentials[0] || $helper->getLicenseEmail() != $credentials[1]) {
            $helper->log('ERROR: Authentication fialed!');
            throw new Exception("Authorization invalid, possible attack!");
        }
    } catch (Exception $e) {
        $helper->log('ERROR: ' . $e->getMessage());
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
            $helper->log($errorString);
        } else {
            if (!$helper->isEnabledCustomerSync()) {
                $helper->log('SKIPING: Customer synchronization disabled');
            } else {
                if (count($objects) == 1) {
                    // Process Realtime
                    $object = $objects[0];
                    // Each object should have 'attributes' property and 'type' inside 'attributes'
                    if (property_exists($object, "attributes") && property_exists($object->attributes, "type")) {
                        try {
                            $_prefix = Mage::helper('tnw_salesforce/salesforce')->getSfPrefix();
                            /* Safer to set the session at this level */
                            Mage::getSingleton('core/session')->setFromSalesForce(true);
                            // Call proper Magento upsert method
                            switch ($object->attributes->type) {
                                case 'Account': //for account when personal account enabled
                                    if ($object->IsPersonAccount != 1) {
                                        break;
                                    }
                                    //we don\'t need break here because we use the same code as next
                                case 'Contact': //or for contact
                                    if ($object->Email || (property_exists($object, 'IsPersonAccount') && $object->IsPersonAccount == 1 && $object->PersonEmail)) {
                                        $helper->log("Synchronizing: " . $object->attributes->type);
                                        //$entity[] = Mage::helper('tnw_salesforce/customer')->contactProcess($object);
                                        Mage::helper('tnw_salesforce/magento_customers')->process($object);
                                    } else {
                                        $helper->log("SKIPPING: Email is missing in Salesforce!");
                                    }
                                    break;
                                case $_prefix . 'Website__c':
                                    if (
                                        property_exists($object, $_prefix . 'Website_ID__c')
                                        && !empty($object->{$_prefix . 'Website_ID__c'})
                                        && property_exists($object, $_prefix . 'Code__c')
                                        && !empty($object->{$_prefix . 'Code__c'})
                                    ) {
                                        Mage::helper('tnw_salesforce/magento_websites')->process($object);
                                    } else {
                                        $helper->log("SKIPPING: Website ID and/or Code is missing in Salesforce!");
                                    }
                                    break;
                                case 'Product2':
                                    if ($object->ProductCode) {
                                        Mage::helper('tnw_salesforce/magento_products')->process($object);
                                    } else {
                                        $helper->log("SKIPPING: ProductCode is missing in Salesforce!");
                                    }
                                    break;
                                default:
                                    $helper->log("Synchronization SKIPPED for: " . $object->attributes->type);
                            }
                            /* Reset session for further insertion */
                            Mage::getSingleton('core/session')->setFromSalesForce(false);
                        } catch (Exception $e) {
                            $helper->log("Error: " . $e->getMessage());
                            $helper->log("Failed to upsert a " . $object->attributes->type . " #" . $object->Id . ", please re-save or re-import it manually");
                        }
                    }
                } else {
                    // Add to Queue
                    /* Save into a db */
                    try {
                        $uid = uniqid("ctmr_", true);
                        $model = Mage::getModel('tnw_salesforce/imports');
                        $model->setId($uid);
                        $model->setJson(serialize($fromSf));
                        $model->setIsProcessing(NULL);
                        unset($objects, $formSf);
                        $model->forceInsert();
                        $helper->log("Import JSON accepted, pending Import");
                        $status = true;
                        unset($uid, $model);
                    } catch (Exception $e) {
                        $errorString = "Could not process JSON, Error: " . $e->getMessage();
                        $helper->log($errorString);
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

$helper->log("Return JSON to Salesforce: " . $return);
$helper->log("========== Sync from Salesforce End ==========");
