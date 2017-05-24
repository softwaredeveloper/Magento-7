<?php

class Cardstream_PaymentGateway_Model_Mysql4_PaymentGateway_Trans extends Mage_Core_Model_Mysql4_Abstract {

	public function _construct() {

		$this->_init('PaymentGateway/PaymentGateway_Trans', 'id');

	}

}