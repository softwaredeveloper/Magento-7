<?php

class Cardstream_PaymentGateway_Model_Mysql4_PaymentGateway_Trans_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract {

	public function _construct() {

		$this->_init('PaymentGateway/PaymentGateway_Trans');

	}

	public function addAttributeToSort($attribute, $dir='asc') {

		if (!is_string($attribute)) {
			return $this;
		}

		$this->setOrder($attribute, $dir);

		return $this;

	}

}