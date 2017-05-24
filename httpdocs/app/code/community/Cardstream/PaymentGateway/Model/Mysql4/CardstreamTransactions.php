<?php

class Cardstream_PaymentGateway_Model_Mysql4_CardstreamTransactions extends Mage_Core_Model_Mysql4_Abstract {

   	public function _construct() {
        $this->_init('PaymentGateway/CardstreamTransactions', 'id');
    }

}

?>
