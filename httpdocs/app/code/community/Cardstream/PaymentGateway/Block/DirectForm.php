<?php

class Cardstream_PaymentGateway_Block_DirectForm extends Mage_Payment_Block_Form {
    protected $_template = "PaymentGateway/Direct.phtml";
    protected $_instance;
    protected function _construct() {
        parent::_construct();
        $this->_instance = Mage::getModel('PaymentGateway/Standard');
    }
    public function getMethodCode(){
        return $this->_instance->getCode();
    }
    public function getMethod(){
        return $this->_instance;
    }
}
