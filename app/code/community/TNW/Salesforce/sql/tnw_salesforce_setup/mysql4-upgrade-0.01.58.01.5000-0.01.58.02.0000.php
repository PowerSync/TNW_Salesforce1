<?php

/* @var $installer Mage_Customer_Model_Entity_Setup */
$installer = Mage::getResourceModel('customer/setup', 'customer_setup');

$installer->startSetup();

$newAttributes = array(
    'first_purchase' => array(
        'type' => Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
        'label' => 'First Purchase Date',
        'input' => 'datetime',

    ),
    'first_transaction_id' => array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'label' => 'First Transaction Id',
    ),
);

foreach ($newAttributes as $code => $newAttributeData) {

//    $installer->removeAttribute('customer', $code);

    $installer->getConnection()->addColumn(
        $installer->getTable('customer/entity'),
        $code,
        array(
            'comment' => (isset($newAttributeData['label']) ? $newAttributeData['label'] : $code),
            'type' => $newAttributeData['type'],
            'length' => (isset($newAttributeData['length']) ? $newAttributeData['length'] : null),
        )
    );

    $installer->addAttribute(
        'customer',
        $code,
        array(
            'label' => (isset($newAttributeData['label']) ? $newAttributeData['label'] : $code),
            'type' => 'static',
            'visible' => true,
            'input' => (isset($newAttributeData['input']) ? $newAttributeData['input'] : ''),
        )
    );

}

$installer->endSetup();