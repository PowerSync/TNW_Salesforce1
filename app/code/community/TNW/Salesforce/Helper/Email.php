<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Email extends TNW_Salesforce_Helper_Abstract
{
    public function sendError($error = NULL, $object = NULL, $_type = NULL)
    {
        $_storeName = Mage::getStoreConfig('general/store_information/name');
        # Cannot connect to SF, execute email

        $mail = new Zend_Mail();
        $body = "<p><b>Alert:</b> " . $_storeName . " experienced the following problem while trying to post data to SalesForce.com</p>";
        $body .= "<p><b>Error:</b> Unable to push the request</p>";
        $body .= "<p><b>Record Information:</b><br /><br />";

        $body .= "</p>";
        $body .= "<p><b>SalesForce Error:</b><br/>";
        $body .= $error;
        $body .= "</p>";
        if ($object) {
            $body .= '<p>' . uc_words($_type) . ' Info:</p><p>';
            foreach (get_object_vars($object) as $key => $line) {
                $body .= "<b>" . $key . ":</b> " . str_replace("\n", "<br/>", $line) . "<br />";
            }
            $body .= "</p>";
        } else {
            $body .= "<p>Unable to extract object information, please refer to the admin tool for info.</p>";
        }
        $body .= "<p>To incorporate this information into SalesForce.com you can key in the data referenced above.</p>";
        $body .= "<p>If you have any questions, please contact the support staff of " . $_storeName . ".</p>";

        $mail->setBodyHtml($body);
        unset($body);
        $mail->setFrom(Mage::getStoreConfig('trans_email/ident_general/email'), Mage::getStoreConfig('trans_email/ident_general/name'));
        $emails = explode(",", Mage::helper('tnw_salesforce')->getFailEmail());
        foreach ($emails as $email) {
            $mail->addTo($email, $email);
        }
        unset($emails, $email);
        $subject = "";
        if (Mage::helper('tnw_salesforce')->getFailEmailPrefix()) {
            $subject = Mage::helper('tnw_salesforce')->getFailEmailPrefix() . " - ";
        }
        $subject .= "Unable to push update from " . $_storeName . " into SalesForce";
        if ($mail->getSubject() !== null) {
            $mail->clearSubject();
        }
        $mail->setSubject($subject);

        try {
            $mail->send();
            unset($mail, $subject);
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("Could not send an email containing the error.");
            Mage::helper('tnw_salesforce')->log("Error: " . $e->getMessage());
            Mage::helper('tnw_salesforce')->log("Error from email: " . $error);
            unset($mail, $subject, $e);
        }
    }
}
