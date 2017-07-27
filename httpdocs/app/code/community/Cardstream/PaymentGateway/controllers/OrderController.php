<?php

class Cardstream_PaymentGateway_OrderController extends Mage_Core_Controller_Front_Action {
	private $instance, $session;
	public function __construct(
		Zend_Controller_Request_Abstract $request,
		Zend_Controller_Response_Abstract $response,
		array $invokeArgs = array()
	) {
		parent::__construct($request, $response, $invokeArgs);
		$this->instance = Mage::getModel('PaymentGateway/Standard');
		$this->session = $this->instance->getSessionData();
		$this->method = $this->instance->method;
	}

	public function isPaymentSubmission() {
		return (
			// Make sure we have something to submit for payment
			$_SERVER['REQUEST_METHOD'] == 'GET' &&
			isset($this->session['form']) &&
			!empty($this->session['form'])
		) || (
			// Check when we have to go through 3DS
			$_SERVER['REQUEST_METHOD'] == 'POST' &&
			isset($_POST['MD']) && (
				isset($_POST['PaRes']) ||
				isset($_POST['PaReq'])
			)
		);
	}

	public function isPaymentResponse() {
		return (
			$_SERVER['REQUEST_METHOD'] == 'POST' &&
			$this->instance->hasKeys(
				$_POST,
				$this->instance->getGenuineResponseHeaders()
			)
		);
	}

	public function processAction() {

		if ($this->method == 'Hosted' && $this->isPaymentSubmission()) {

			$this->instance->redirectPayment();

		} else if ($this->method == 'Direct' && $this->isPaymentSubmission()) {

			$this->instance->sendDirectPayment();

		} else if ($this->method == 'Hosted' && $this->isPaymentResponse()) {

			$this->instance->processAll($_POST);

		} else {
			$this->instance->dbg(
				sprintf(
					'Unknown %s request using %s integration.',
					$_SERVER['REQUEST_METHOD'],
					$this->method
				), true
			);
			$this->instance->clearData();
			$className = get_class($this->instance);
			$responseError = constant("{$className}::PROCESS_ERROR");
			$invalidRequest = constant("{$className}::INVALID_REQUEST");
			$error = sprintf($responseError, $invalidRequest);
			$this->instance->session->addError($error);
			Mage::app()->getResponse()->setRedirect(Mage::getUrl("checkout/cart"));

		}

	}
}

?>
