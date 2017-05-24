<?php
class Cardstream_PaymentGateway_Model_System_Config_Source_IntegrationMethod {
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'Hosted', 'label' => 'Hosted'),
            array('value' => 'Direct', 'label' => 'Direct'),
        );
    }
}
?>
