<?php

class TNW_Salesforce_Helper_Order_Notes extends TNW_Salesforce_Helper_Order
{
    protected $_mySforceConnection = NULL;

    public function process($comment = NULL, $order = NULL, $type = NULL)
    {
        if ($comment->getSalesforceId() || $comment->getComment() == '' || !$order->getSalesforceId()) {
            return; // SKIP
        }
        $this->_mySforceConnection = Mage::helper('tnw_salesforce/salesforce_data')->getClient();
        if (!$this->_mySforceConnection) {
            Mage::getModel('tnw_salesforce/tool_log')->saveNotice("SKIPPING: Salesforce connection failed!");
            return;
        }
        if (!$comment) {
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Cannot update Note - Note object is missing");
            return false;
        }
        if (!$order) {
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Cannot update Note - Order object is missing");
            return false;
        }
        Mage::getModel('tnw_salesforce/tool_log')->saveTrace("------------------- Notes Start -------------------");

        $note = new stdClass();
        $note->ParentId = $order->getSalesforceId();
        //$note->OwnerId = $customerId; //Needs to be Salesforce User
        $note->IsPrivate = false;
        $note->Body = utf8_encode($comment->getComment());

        if (strlen($comment->getComment()) > 75) {
            $note->Title = utf8_encode(substr($comment->getComment(), 0, 75) . '...');
        } else {
            $note->Title = utf8_encode($comment->getComment());
        }

        unset($opportunityId, $contactId);
        /* Dump to Logs */
        foreach ($note as $key => $_value) {
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Note Object: " . $key . " = '" . $_value . "'");
        }

        Mage::dispatchEvent("tnw_salesforce_note_send_before",array("data" => array($note)));
        $response = $this->_mySforceConnection->upsert('Id', array($note), 'Note');
        Mage::dispatchEvent("tnw_salesforce_note_send_after",array(
            "data" => array($note),
            "result" => $response
        ));

        if (!$response[0]->success) {
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Failed to upsert Note!");
            if (is_array($response[0]->errors)) {
                foreach ($response[0]->errors as $_error) {
                    Mage::getModel('tnw_salesforce/tool_log')->saveError("ERROR: " . $_error->message);
                }
            } else {
                Mage::getModel('tnw_salesforce/tool_log')->saveError("ERROR: " . $response[0]->errors->message);
            }
        } else {
            $_updateComment = true;
            switch ($type) {
                case 'Order':
                    $_tableUpdate = 'sales_flat_order_status_history';
                    break;
                default:
                    $_updateComment = false;
            }
            if ($_updateComment) {
                Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Updating table: " . $_tableUpdate);
                try {
                    $_writer = Mage::helper('tnw_salesforce/salesforce_data')->getWriter();
                    $sql = "UPDATE `" . Mage::helper('tnw_salesforce')->getTable($_tableUpdate) . "` SET salesforce_id = '" . $response[0]->id . "' WHERE entity_id = '" . $comment->getId() . "';";
                    Mage::getModel('tnw_salesforce/tool_log')->saveTrace($sql);
                    $_writer->query($sql);
                } catch (Exception $e) {
                    Mage::getModel('tnw_salesforce/tool_log')->saveError("Failed to update table '" . $_tableUpdate . "': " . $e->getMessage());
                    unset($e);
                }
            }
            Mage::getModel('tnw_salesforce/tool_log')->saveTrace("Note #" . $response[0]->id . " upserted...");
        }
        unset($response);
        Mage::getModel('tnw_salesforce/tool_log')->saveTrace("------------------- Note End -------------------");
    }
}