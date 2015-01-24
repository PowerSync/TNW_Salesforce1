<?php

class TNW_Salesforce_Model_Email_Template extends Mage_Core_Model_Email_Template
{

    public function sendTransactional($templateId, $sender, $email, $name, $vars = array(), $storeId = null)
    {
        try {
            if (
                $templateId == 'contacts_email_email_template'
                && is_array($vars)
                && array_key_exists('data', $vars)
            ) {
                Mage::dispatchEvent('tnw_contact_form_submit', array('form' => $vars['data']));
            }
        } catch (Exception $e) {
            Mage::helper('tnw_salesforce')->log("ERROR: Sending email failed... " . $e->getMessage());
        }

        return parent::sendTransactional($templateId, $sender, $email, $name, $vars, $storeId);
    }
}