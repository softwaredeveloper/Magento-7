<?php

class Cardstream_PaymentGateway_Block_HostedForm extends Mage_Payment_Block_Form {
	protected $_template = "PaymentGateway/Hosted.phtml";
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
