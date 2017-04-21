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
		$this->session = $this->instance->session;
	}
	/**
     * Whether a request is valid
     * @param  [type]  $data [description]
     * @return boolean       [description]
     */
	public function isValidRequest() {
		return $this->instance->hasKeys(
				$this->session->getData(),
				$this->instance->getGenuineRequestHeaders()
			) &&
			$this->session->getMethod() == $this->instance->getCode() &&
			$this->instance->isWithinMinutes($this->session->getCTime(), 10);
	}
	/**
     * Whether a response is valid
     * @param  Array  $data response data
     * @return boolean      validity of response
     */
	public function isValidResponse() {
		return $this->instance->hasKeys(
			$_POST,
			$this->instance->getGenuineResponseHeaders()
		);
	}

	public function isPOST() {
		return $_SERVER['REQUEST_METHOD'] == 'POST';
	}

	public function isGET() {
		return $_SERVER['REQUEST_METHOD'] == 'GET';
	}

	public function processAction() {
		if(
			$this->instance->method == 'Hosted' &&
			$this->isValidRequest() &&
			$this->isGET()
		) {
			//Redirect the valid request to the hosted form
			//Create the hosted form and POST it to the hosted gateway
			$this->instance->redirectPayment();
		} else if (
			$this->instance->method == 'Hosted' &&
			$this->isValidResponse() &&
			$this->isPOST()
		) {
			//Use the hosted response to process the payment
			$this->instance->processAll($_POST);
		} else if (
			$this->instance->method == 'Direct' &&
			$this->isValidRequest() &&
			$this->isGET()
		) {
			//Try to process a direct payment
			$template = $this->instance->createDirectRequest();
			//Rebuild the data by merging the template of a direct request to retrieve only the saved form data during checkout
			$req = array_merge(
				$template,
				$this->instance->retrieveSpecificKeys(
					$this->session->getData(),
					array_keys($template)
				)
			);
			//Create the signature at the end, preventing any signature slips when gettings submitted through the client
			$req['signature'] = $this->instance->createSignature($req, $this->instance->secret);
			//Process the quest using curl
			$res = $this->instance->makeRequest(MODULE_PAYMENT_CARDSTREAM_DIRECT_URL, $req);
			//echo "<pre>" . var_export($req, true) . "</pre><br/><pre>" . var_export($res, true) . "</pre>";
			$this->instance->processAll($res);
		} else {
			$this->instance->clearData();
		}
	}
}

?>