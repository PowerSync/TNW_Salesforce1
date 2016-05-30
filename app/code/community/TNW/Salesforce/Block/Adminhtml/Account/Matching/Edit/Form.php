<?php

class TNW_Salesforce_Block_Adminhtml_Account_Matching_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Prepare form before rendering HTML
     *
     * @return TNW_Salesforce_Block_Adminhtml_Account_Matching_Edit_Form
     */
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array(
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', array('matching_id' => $this->getRequest()->getParam('matching_id'))),
            'method' => 'post',
            'enctype' => 'multipart/form-data'
        ));
        $form->setUseContainer(true);
        $this->setForm($form);

        $formValues = array();
        if (Mage::getSingleton('adminhtml/session')->getAccountData()) {
            $formValues = Mage::getSingleton('adminhtml/session')->getAccountData();
            Mage::getSingleton('adminhtml/session')->getAccountData(null);
        } elseif (Mage::registry('salesforce_account_matching_data')) {
            $formValues = Mage::registry('salesforce_account_matching_data')->getData();
        }

        /** @var TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection $collection */
        $collection = Mage::getResourceModel('tnw_salesforce_api_entity/account_collection')
            ->setPageSize(1);

        if (isset($formValues['account_id'])) {
            $collection->addFieldToFilter('Id', array('eq'=>$formValues['account_id']));
        }

        if (Mage::helper('tnw_salesforce')->usePersonAccount()) {
            $collection->getSelect()
                ->where('IsPersonAccount = false');
        }

        $select2Url = $this->getUrl('*/*/search');

$select2Html = <<<HTML
<script type="application/javascript">
    (function($) {
        $(document).ready(function() {
            $(".tnw-ajax-find-select").select2({
              ajax: {
                url: "{$select2Url}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                  return {
                    q: params.term,
                    page: params.page
                  };
                },
                processResults: function (data, params) {
                  params.page = params.page || 1;

                  return {
                    results: data.items,
                    pagination: {
                      more: (params.page * 30) < data.totalRecords
                    }
                  };
                },
                cache: true
              },
              minimumInputLength: 4
            });
        });
    })(jQuery);
</script>
HTML;

        $fieldset = $form->addFieldset('account_matching', array('legend' => $this->__('Rule Information')));
        $fieldset->addField('account_id', 'select', array(
            'label' => $this->__('Account Name'),
            'name' => 'account_id',
            'style' => 'width:273px',
            'values' => $collection->setFullIdMode(true)->getAllOptions(),
            'class' => 'tnw-ajax-find-select',
            'required' => true,
            'after_element_html' => $select2Html
        ));

        $fieldset->addField('email_domain', 'text', array(
            'label' => $this->__('Email Domain'),
            'style' => 'width:273px',
            'name' => 'email_domain',
            'required' => true,
        ));

        $form->setValues($formValues);
        return parent::_prepareForm();
    }

}
