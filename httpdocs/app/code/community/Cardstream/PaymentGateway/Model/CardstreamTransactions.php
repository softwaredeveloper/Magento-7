<?php

class Cardstream_PaymentGateway_Model_CardstreamTransactions extends Mage_Core_Model_Abstract {

   	public function _construct() {
        $this->_init('PaymentGateway/CardstreamTransactions');
    }

    public function loadById($Id){
        $this->load($Id, 'id');
        return $this;
    }

}

?>