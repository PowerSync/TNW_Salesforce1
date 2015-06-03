<?php

class TNW_Salesforce_Block_Adminhtml_System_Config_Form_Field_Array_Carrier
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('salesforce/config/form/field/array/carrier.phtml');
    }

    /**
     * Obtain existing data from form element
     *
     * Each row will be instance of Varien_Object
     *
     * @return array
     */
    public function getArrayRows()
    {
        if (null !== $this->_arrayRowsCache) {
            return $this->_arrayRowsCache;
        }
        $result = array();

        $carriers = Mage::getSingleton('shipping/config')->getActiveCarriers();
        if (!empty($carriers)) {
            /** @var Varien_Data_Form_Element_Abstract */
            $element = $this->getElement();

            $prepareData = array();
            foreach ($carriers as $code => $carrierModel) {
                /** @var Mage_Shipping_Model_Carrier_Flatrate $carrierModel */
                $prepareData[$code] = new Varien_Object(array(
                    'carrier' => $code,
                    'account' => '',
                    '_id' => uniqid(),
                ));
            }

            if ($element->getValue() && is_array($element->getValue())) {
                foreach ($element->getValue() as $rowId => $row) {
                    if (array_key_exists($row['carrier'], $prepareData)) {
                        $prepareData[$row['carrier']]['account'] = $row['account'];
                        $prepareData[$row['carrier']]['_id'] = $rowId;
                    }
                }
            }

            foreach ($prepareData as $dataRow) {
                $result[$dataRow['_id']] = $dataRow;
                $this->_prepareArrayRow($result[$dataRow['_id']]);
            }
        }

        $this->_arrayRowsCache = $result;
        return $this->_arrayRowsCache;
    }

    /**
     * Add columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn('carrier', array(
            'label' => $this->__('Carrier'),
            'style' => 'width: 200px',
            'type' => 'label',
        ));

        $this->addColumn('account', array(
            'label' => $this->__('Account'),
            'style' => 'width: 300px',
            'type' => 'select',
            'options' => $this->helper('tnw_salesforce/config')->getSalesforceAccounts(),
        ));

        $this->_addAfter = false;
    }

    /**
     * Add a column to array-grid
     *
     * @param string $name
     * @param array $params
     */
    public function addColumn($name, $params)
    {
        $this->_columns[$name] = array(
            'label'     => empty($params['label'])      ? 'Column'  : $params['label'],
            'size'      => empty($params['size'])       ? false     : $params['size'],
            'style'     => empty($params['style'])      ? null      : $params['style'],
            'class'     => empty($params['class'])      ? null      : $params['class'],
            'type'      => empty($params['type'])       ? 'text'    : $params['type'],
            'options'   => empty($params['options'])    ? array()   : $params['options'],
        );
    }

    /**
     * Render array cell for prototypeJS template
     *
     * @param string $columnName
     * @return string
     * @throws Exception
     */
    protected function _renderCellTemplate($columnName)
    {
        if (empty($this->_columns[$columnName])) {
            throw new Exception('Wrong column name specified.');
        }
        $column     = $this->_columns[$columnName];
        $inputName  = $this->getElement()->getName() . '[#{_id}][' . $columnName . ']';

        $html = '';
        switch ($column['type']) {
            case 'select':
                $html .= '<select type="text" name="' . $inputName . '" value="#{' . $columnName . '}" ' .
                    ($column['size'] ? 'size="' . $column['size'] . '"' : '') . ' class="' .
                    (isset($column['class']) ? $column['class'] : 'input-text') . '"'.
                    (isset($column['style']) ? ' style="'.$column['style'] . '"' : '') . '/>' .
                    '<option value=""></option>';

                //current dropdown value is selected in config array custom template
                foreach ($column['options'] as $accountId => $name) {
                    $html .= sprintf('<option value="%s">%s</option>', $accountId, $this->escapeHtml($name));
                }

                $html .= '</select>';
                break;
            case 'label':
                $html .= '<input type="hidden" name="' . $inputName . '" value="#{' . $columnName . '}" ' .
                    ($column['size'] ? 'size="' . $column['size'] . '"' : '') . ' class="' .
                    (isset($column['class']) ? $column['class'] : 'input-text') . '"'.
                    (isset($column['style']) ? ' style="'.$column['style'] . '"' : '') . '/>' .
                    '<strong>#{' . $columnName . '}</strong>';
                    break;
            case 'text':
            default:
            $html .= '<input type="text" name="' . $inputName . '" value="#{' . $columnName . '}" ' .
                ($column['size'] ? 'size="' . $column['size'] . '"' : '') . ' class="' .
                (isset($column['class']) ? $column['class'] : 'input-text') . '"'.
                (isset($column['style']) ? ' style="'.$column['style'] . '"' : '') . '/>';
        }

        return $html;
    }
}