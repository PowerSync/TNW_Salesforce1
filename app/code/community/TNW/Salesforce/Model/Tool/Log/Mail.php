<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 29.10.15
 * Time: 14:16
 */
class TNW_Salesforce_Model_Tool_Log_Mail  extends Varien_Object
{
    /**
     * @comment send Email with error message
     */
    public function send()
    {
        if (Mage::helper('tnw_salesforce/config')->getFailEmail()) {
            $filename = Mage::getBaseDir('log') . DS . Mage::getModel('tnw_salesforce/tool_log_file')->prepareFilename(null, Zend_Log::CRIT);

            if (!file_exists($filename) || filesize($filename) == 0) {
                /**
                 * if no error logs to send
                 */
                return false;
            }


            $_storeName = Mage::getStoreConfig('general/store_information/name');
            # Cannot connect to SF, execute email


            $mail = new Zend_Mail();
            $body = "<p><b>Alert:</b> " . $_storeName . " experienced the following problem while trying to post data to SalesForce.com</p>";
            $body .= "<p><b>Error:</b> Unable to push the request</p>";
            $body .= "<p><b>Record Information:</b><br /><br />";

            $body .= "</p>";
            $body .= "<p><b>SalesForce Error:</b><br/>";
            $body .= file_get_contents($filename);
            $body .= "</p>";

            $body .= "<p>To incorporate this information into SalesForce.com you can key in the data referenced above.</p>";
            $body .= "<p>If you have any questions, please contact the support staff of " . $_storeName . ".</p>";

            $mail->setBodyHtml($body);
            unset($body);
            $mail->setFrom(Mage::getStoreConfig('trans_email/ident_general/email'), Mage::getStoreConfig('trans_email/ident_general/name'));
            $emails = explode(",", Mage::helper('tnw_salesforce/config')->getFailEmail());
            foreach ($emails as $email) {
                $mail->addTo($email, $email);
            }
            unset($emails, $email);
            $subject = "";
            if (Mage::helper('tnw_salesforce/config')->getFailEmailPrefix()) {
                $subject = Mage::helper('tnw_salesforce/config')->getFailEmailPrefix() . " - ";
            }
            $subject .= "Unable to push update from " . $_storeName . " into SalesForce";
            if ($mail->getSubject() !== null) {
                $mail->clearSubject();
            }
            $mail->setSubject($subject);

            try {
                $mail->send();
                $ioAdapter = Mage::getModel('tnw_salesforce/varien_io_file');
                $ioAdapter->rm($filename);

            } catch (Exception $e) {
                Mage::getModel('tnw_salesforce/tool_log_file')->write(
                    sprintf('Could not send an email containing the error. Error from email: %s', $e->getMessage()),
                    Zend_Log::ERR
                );
            }
        }
    }

}