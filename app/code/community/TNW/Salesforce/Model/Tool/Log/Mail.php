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
    const XML_PATH_EMAIL_TEMPLATE    = 'salesforce/developer/template';
    const XML_PATH_EMAIL_IDENTITY    = 'salesforce/developer/identity';
    const XML_PATH_EMAIL_RECIPIENT   = 'salesforce/developer/fail_order';
    const XML_PATH_EMAIL_COPY_TO     = 'salesforce/developer/copy_to';
    const XML_PATH_EMAIL_COPY_METHOD = 'salesforce/developer/copy_method';
    const XML_PATH_EMAIL_ENABLED     = 'salesforce/developer/enabled';

    /**
     * @comment send Email with error message
     */
    public function send()
    {
        if (Mage::getStoreConfigFlag(self::XML_PATH_EMAIL_ENABLED) && Mage::helper('tnw_salesforce/config')->getFailEmail()) {
            $filename = Mage::getBaseDir('log') . DS . Mage::getModel('tnw_salesforce/tool_log_file')->prepareFilename(null, Zend_Log::CRIT);

            if (!file_exists($filename) || filesize($filename) == 0) {
                /**
                 * if no error logs to send
                 */
                return false;
            }

            // Get the destination email addresses to send copies to
            $copyTo = $this->_getEmails(self::XML_PATH_EMAIL_COPY_TO);
            $copyMethod = Mage::getStoreConfig(self::XML_PATH_EMAIL_COPY_METHOD);

            /** @var $mailer Mage_Core_Model_Email_Template_Mailer */
            $mailer = Mage::getModel('core/email_template_mailer');
            /** @var $emailInfo Mage_Core_Model_Email_Info */
            $emailInfo = Mage::getModel('core/email_info');
            $emailInfo->addTo(Mage::helper('tnw_salesforce/config')->getFailEmail());
            if ($copyTo && $copyMethod == 'bcc') {
                // Add bcc to customer email
                foreach ($copyTo as $email) {
                    $emailInfo->addBcc($email);
                }
            }
            $mailer->addEmailInfo($emailInfo);

            // Email copies are sent as separated emails if their copy method is 'copy'
            if ($copyTo && $copyMethod == 'copy') {
                foreach ($copyTo as $email) {
                    $emailInfo = Mage::getModel('core/email_info')
                        ->addTo($email);

                    $mailer->addEmailInfo($emailInfo);
                }
            }

            // Set all required params and send emails
            $mailer
                ->setSender(Mage::getStoreConfig(self::XML_PATH_EMAIL_IDENTITY))
                ->setTemplateId(Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE))
                ->setTemplateParams(array(
                    'prefix'  => Mage::helper('tnw_salesforce/config')->getFailEmailPrefix(),
                    'content' => file_get_contents($filename)
                ));

            /** @var $emailQueue Mage_Core_Model_Email_Queue */
            $emailQueue = Mage::getModel('core/email_queue');
            if (!is_bool($emailQueue)) {
                $emailQueue->setEntityId(null)
                    ->setEntityType('salesforce_notification')
                    ->setEventType('new_salesforce_notification');

                try {
                    $mailer->setQueue($emailQueue)->send();
                    $ioAdapter = Mage::getModel('tnw_salesforce/varien_io_file');
                    $ioAdapter->rm($filename);

                } catch (Exception $e) {
                    Mage::getModel('tnw_salesforce/tool_log_file')->write(
                        sprintf('Could not send an email containing the error. Error from email: %s', $e->getMessage()),
                        Zend_Log::ERR
                    );
                }
            } else {
                // TODO: part of PCMI-1302
            }

        }

        return true;
    }

    /**
     * @param $configPath
     * @return array|bool
     */
    protected function _getEmails($configPath)
    {
        $data = Mage::getStoreConfig($configPath);
        if (!empty($data)) {
            return explode(',', $data);
        }
        return false;
    }
}