<?php

class Cardstream_PaymentGateway_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	const _MODULE = 'Cardstream_PaymentGateway';
	const DIRECT_URL = 'https://gateway.cardstream.com/direct/';
	const HOSTED_URL = 'https://gateway.cardstream.com/hosted/';
	const VERIFY_ERROR = 'The signature provided in the response does not match. This response might be fraudulent';
	const PROCESS_ERROR = 'Sorry, we are unable to process this order (reason: %s). Please correct any faults and try again.';
	const SERVICE_ERROR = 'SERVICE ERROR - CONTACT ADMIN';
	const INVALID_REQUEST = 'INVALID REQUEST';
	const INSECURE_ERROR = 'The %s module cannot be used under an insecure host and has been hidden for user protection';

	protected $_code  = 'PaymentGateway_standard';
	protected $_infoBlockType = 'payment/info';
	protected $_formBlockType;
	protected $_isGateway               = true;
	protected $_canCapture              = true;
	protected $_canUseInternal          = true;
	protected $_canUseCheckout          = true;
	protected $_canCapturePartial       = true;
	protected $_isInitializeNeeded      = true;
	protected $_canUseForMultishipping  = true;
	protected $_canSaveCc               = false;
	protected $_canRefund               = false;
	protected $_canVoid                 = false;

	private $merchantID, $secret, $canDebug;

	public $method, $session, $responsive, $countryCode, $currencyCode;

	public function __construct(){
		$this->method = $this->getConfigData('IntegrationMethod');
		$this->_formBlockType = "PaymentGateway/{$this->method}Form";
		$this->countryCode = $this->getConfigData('CountryCode');
		$this->currencyCode = $this->getConfigData('CurrencyCode');
		$this->secret = trim($this->getConfigData('MerchantSharedKey'));
		$this->merchantID = $this->getConfigData('MerchantID');
		$this->responsive = $this->getConfigData('FormResponsive') ? 'Y' : 'N';
		$this->canDebug = (boolean)$this->getConfigData('Debug');
		$this->session = $this->getCoreSession();

		if(!$this->isSecure() && $this->method == 'Direct') {
			$this->_canUseCheckout = false;
			$error = sprintf(self::INSECURE_ERROR, self::_MODULE);
			$this->log($error, true);
		}
	}

	/**
	 * Is the server running securely?
	 * Either check that we are running SSL with the setting defined
	 * as ENABLE_SSL or eitherway if it's currently running at all
	 * @return boolean Whether the server is secure
	 */
	public function isSecure() {
		return ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')|| (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') || (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https'));
	}

	/**
	 * Returns a list of keys found with all genuine responses
	 * @return Array response keys
	 */
	public function getGenuineResponseHeaders() {
		return array(
			'orderRef',
			'signature',
			'responseCode',
			'transactionUnique',
			'responseMessage',
			'action'
		);
	}

	/**
	 * Save any assigned POST['payment'] data to our session as we can't get it any other way :(
	 * Our payment data is then stored as named input fields (e.g. payment[merchantID])
	 * MAKE SURE TO REMOVE ALL PAYMENT INFORMATION AFTER PAYMENT (using $this->clearData();)
	 * @param  Varien_Object $data POST DATA
	 * @return NULL
	 */
	public function assignData($data) {
		$this->log('Assigning form data for checkout');
		// Get the current active session
		$session = $this->session->getData();
		// Clear any data inside
		if (isset($session[self::_MODULE])) {
			unset($session[self::_MODULE]['form']);
		}
		// Get all the payment data from the form
		$paymentData = $data->getData();
		// Always remove 'checks' as part of Magento One-Page Checkout
		unset($paymentData['checks']);
		unset($paymentData[$this->_code]);
		// Set the payment form data to the session
		$session[self::_MODULE]['form'] = $paymentData;
		$this->session->setData($session);
	}

	/**
	 * Get the data stored by our payment module
	 * rather than all session information
	 * @return array
	 */
	public function getSessionData() {
		$session = $this->session->getData();
		return (isset($session[self::_MODULE]) ? $session[self::_MODULE] : array());
	}

	/**
	 * Remove ALL payment session information
	 */
	public function clearData() {
		$this->log('Clearing any remaining checkout data');
		$session = $this->session->getData();
		unset($session[self::_MODULE]);
		$this->session->setData($session);
	}

	/**
	 * Custom function created to store the order details
	 * (e.g. billing address, order reference, etc) so that
	 * we can simply add it to the redirection in a hosted
	 * form or to a direct request easily.
	 *
	 * Beforehand, we used to store this information in the
	 * session along with any other data which made it much
	 * harder for us to find what the actual data was. We
	 * were regenerating the information as much as we could
	 * and used array_merge to try and make sure we had all
	 * the information available to ourself. This made it
	 * much harder to track how and where the information was
	 * being handled.
	 *
	 * This time we generate the order information into our
	 * own array block inside the session known as _SAVE.
	 * Making sure to seperate this information and the form
	 * submitted by the user. We can then merge the arrays
	 * into the original order capture without hassle.
	 *
	 * @return void
	 */
	public function startOrderCapture() {
		$this->log('Building order request for payment');
		// Capture the order information into the session ready for payment
		$session = $this->session->getData();
		// Any old information needs to be overwritten at this stage.
		unset($session[self::_MODULE]);

		$quote = $this->getQuote();
		$totals = $quote->getTotals();
		$amount = bcmul(round($totals['grand_total']->getValue(), 2), 100);
		$ref = $quote->getUpdatedAt() . " - " . $quote->getId();
		$billingAddress = $quote->getBillingAddress();

		//Create a formatted address
		$address = ($billingAddress->getStreet(1) ? $billingAddress->getStreet(1) . ",\n" : '');
		$address .= ($billingAddress->getStreet(2) ? $billingAddress->getStreet(2) . ",\n" : '');
		$address .= ($billingAddress->getCity() ? $billingAddress->getCity() . ",\n" : '');
		$address .= ($billingAddress->getRegion() ? $billingAddress->getRegion() . ",\n" : '');
		$address .= ($billingAddress->getCountry() ? $billingAddress->getCountry() : '');

		$req = array(
			'merchantID'        => $this->merchantID,
			'amount'            => $amount,
			'transactionUnique' => uniqid(),
			'orderRef'          => $ref,
			'countryCode'       => $this->countryCode,
			'currencyCode'      => $this->currencyCode,
			'customerName'      => $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
			'customerAddress'   => $address,
			'customerPostCode'  => $billingAddress->getPostcode(),
			'customerEmail'     => $billingAddress->getEmail(),
			'remoteAddress'     => $_SERVER['REMOTE_ADDR']
		);

		if ($this->method == "Direct") {
			$req['action'] = 'SALE';
			$req['type'] = 1;
		}

		if(!is_null($billingAddress->getTelephone())) {
			$req["customerPhone"] = $billingAddress->getTelephone();
		}

		$session[self::_MODULE] = array('req' => $req);
		$this->session->setData($session);

	}

	/**
	 * Redirect the user to the hosted form using the captured order information
	 * @return void
	 */
	public function redirectPayment() {
		$this->log('Redirecting user for payment');
		// Add hosted parameters to the generic order information
		$session = $this->session->getData();
		$req = array_merge(
			$session[self::_MODULE]['req'],
			array(
				'redirectURL' => $this->getOrderPlaceRedirectUrl(),
				'callbackURL' => $this->getOrderPlaceRedirectUrl(),
				'formResponsive' => $this->responsive
			)
		);

		// Comment session data
		$this->log($this->commentSessionData());

		$req['signature'] = $this->createSignature($req, $this->secret);

		// Always clear to prevent redirects after
		$this->clearData();

		echo "<form id='redirect' action='" . self::HOSTED_URL . "' method='POST'>";
		// Get session stored keys for a hosted request
		foreach ($req as $key => $value) {
			echo "<input type='hidden' name='$key' value='$value'/>";
		}
		echo "</form>";
		echo "<script>document.onreadystatechange = () => {document.getElementById('redirect').submit();}</script>";
	}
	private function getDirectDetails() {
		$this->log('Normalizing form data');
		$session = $this->getSessionData();
		$required = array('cardNumber', 'cardStripCode', 'cardExpiryYear', 'cardExpiryMonth');
		$req = array();
		foreach ($required as $index=>$key) {
			if (isset($session['form'][$key])) {
				$req[$key] = $session['form'][$key];
			} else {
				$this->log("Missing key '{$key}' from checkout form", true);
				$error = sprintf(self::PROCESS_ERROR, 'MISSING ' . strtoupper($key));
				$this->instance->session->addError($error);
				Mage::app()->getResponse()->setRedirect(Mage::getUrl("checkout/cart"));
				return;
			}
		}
		return $req;
	}
	/**
	 * Setup a direct payment and send it to the right place
	 * @return void
	 */
	public function sendDirectPayment() {
		//Try to process a direct payment
		$session = $this->getSessionData();
		$formData = $this->getDirectDetails();
		$req = array_merge(
			$session['req'],
			$formData
		);

		if (isset($_REQUEST['MD'])) {
			$req['threeDSMD'] = $_REQUEST['MD'];
		}
		if (isset($_REQUEST['PaRes'])) {
			$req['threeDSPaRes'] = $_REQUEST['PaRes'];
		}
		if (isset($_REQUEST['PaReq'])) {
			$req['threeDSPaReq'] = $_REQUEST['PaReq'];
		}

		// Comment session data
		$this->log($this->commentSessionData());

		$req['signature'] = $this->createSignature($req, $this->secret);
		$res = $this->makeRequest(self::DIRECT_URL, $req);
		$this->log('Verifying response from gateway');
		if (!$this->hasKeys($res, $this->getGenuineResponseHeaders())) {
			$this->log('Unable to verify response as one or more keys are missing');
			$this->clearData();
			$error = sprintf(self::PROCESS_ERROR, self::INVALID_REQUEST);
			$this->session->addError($error);
			Mage::app()->getResponse()->setRedirect(Mage::getUrl("checkout/cart"));
		}
		$this->processAll($res);
	}

	/**
	 * Process a payment response and
	 * @param  array		$data		The response from the payment gateway
	 * @return void
	 */
	public function processAll($data) {
		$this->log('Processing payment response');
		// This is a valid response to save payment details
		$sig = isset($data['signature']) ? $data['signature'] : null;
		unset($data['signature']);
		if ($sig === $this->createSignature($data, $this->secret)) {
			$this->log('Signature successfully verified');
			if ($data['responseCode'] == 65802) {
				$this->log('A 3DS response was returned. The form must be completed to continue the transaction.');
				// 3D Secure process must complete
				$pageUrl = ($this->isSecure() ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . $_SERVER["REQUEST_URI"];
				die("
					<script>document.onreadystatechange = function() { document.getElementById('3ds').submit(); }</script>
					<form id='3ds' action=\"" . htmlentities($data['threeDSACSURL']) . "\" method=\"POST\">
						<input type=\"hidden\" name=\"MD\" value=\"" . htmlentities($data['threeDSMD']) . "\">
						<input type=\"hidden\" name=\"PaReq\" value=\"" . htmlentities($data['threeDSPaReq']) . "\">
						<input type=\"hidden\" name=\"TermUrl\" value=\"" . htmlentities($pageUrl) . "\">
					</form>
				");
			} else {
				$quoteId = preg_replace(
					"(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} - )",
					"",
					$data['orderRef']
				);
				$quote = Mage::getSingleton('sales/quote')->load($quoteId);
				$orderId = $quote->getReservedOrderId();
				$order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);
				// Process a successful transaction
				if ($data['responseCode'] == 0){
					$this->log('Payment successful - ammending order details');
					// Save payment information
					$amount = bcdiv($data['amountReceived'], 100, 2);
					$payment = $order->getPayment();
					// Set order status
					$status = Mage::getStoreConfig('payment/PaymentGateway_standard/successfulpaymentstatus');
					if($order->getStatus() != $status) {
						$order->setBaseTotalPaid($amount)->setTotalPaid($amount);
						/*
						 * Please see /app/core/Mage/Payment/Model/Method/Cc.php assignData method
						 */
						$payment
						->setCardstreamTransactionUnique($data['transactionUnique'])
						->setCardstreamOrderRef($data['orderRef'])
						->setCardstreamXref($data['xref'])
						->setCardstreamResponseMessage($data['responseMessage'])
						->setCardstreamAuthorisationCode($data['authorisationCode'])
						->setCardstreamAmountReceived($data['amountReceived'])
						->save();
						// Create invoice if we can
						$order->setStatus($status);
						$order->addStatusToHistory(
							$status,
							$this->buildStatus($data),
							0
						);
						if ($order->canInvoice()) {
							$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
							if ($invoice->getTotalQty()) {
								$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
								$invoice->register();
								$transactionSave = Mage::getModel('core/resource_transaction')
									->addObject($invoice)
									->addObject($invoice->getOrder());
								$transactionSave->save();
								$order->sendNewOrderEmail();
								$invoice->sendEmail();
								$invoice->save();
							} else {
								$order->sendNewOrderEmail();
							}
						} else {
							$order->sendNewOrderEmail();
						}
						$order->save();
					}
					$this->clearData();
					Mage::app()->getResponse()->setRedirect(Mage::getUrl("checkout/onepage/success"));
				} else {
					if ($order) {
						$this->log('Payment was unsuccessful - ' . $data['responseMessage']);
						$status = Mage::getStoreConfig('payment/PaymentGateway_standard/unsuccessfulpaymentstatus');
						$order->setStatus($status);
						$order->addStatusToHistory(
							$status,
							$this->buildStatus($data),
							0
						);
						$payment = $order->getPayment();
						$payment
						->setCardstreamTransactionUnique($data['transactionUnique'])
						->setCardstreamOrderRef($data['orderRef'])
						->setCardstreamXref($data['xref'])
						->setCardstreamResponseMessage($data['responseMessage'])
						->save();
						try {
							$order->cancel();
						} catch (Exception $e){
							Mage::Log($e);
						}
						$order->save();
						try {
							$order->delete();
						} catch (Exception $e) {
							// Admins and admin areas can only delete failed orders
						}
					}
					$this->clearData();
					$quote->setIsActive(true)->save();
					$this->getCheckout()->setQuoteId($quoteId);
					$error = sprintf(self::PROCESS_ERROR, htmlentities($data['responseMessage']));
					$this->session->addError($error);
					Mage::app()->getResponse()->setRedirect(Mage::getUrl("checkout/cart"));
				}
			}
		} else {
			$this->clearData();
			$this->log('Signature could not be verified');
			if ($data['responseCode'] !== 0) {
				$error = sprintf(self::PROCESS_ERROR, $data['responseCode'] . ' - ' . $data['responseMessage']);
				try {
					$quoteId = preg_replace(
						"(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} - )",
						"",
						$data['orderRef']
					);
					$quote = Mage::getSingleton('sales/quote')->load($quoteId);
					$quote->setIsActive(true)->save();
					$orderId = $quote->getReservedOrderId();
					$order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);
					$order->delete();
				} catch (Exception $e){
					// It may not exist yet
				}
			} else {
				$error = self::VERIFY_ERROR;
			}
			$this->session->addError($error);
			Mage::app()->getResponse()->setRedirect(Mage::getUrl("checkout/cart"));
		}
	}


	/**
	 * Make a curl request to a URL with POST data
	 * @param  String $url The URL to send data to
	 * @param  Array $req  The request data
	 * @return Array       The response data
	 */
	public function makeRequest($url, $req) {
		$this->log("Making request to " . $url);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($req));
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		parse_str(curl_exec($ch), $res);
		curl_close($ch);
		if ($res !== null && $res != '') {
			$this->log('Received response to request');
		} else {
			$this->log('Either the request failured or an empty response was received');
		}
		return $res;
	}
	/**
	 * Sign requests with a SHA512 hash
	 * @param  Array  $data Request data
	 * @param  String $key  The secret to use as part of the signature
	 * @return String       The finished signature
	 */
	public function createSignature(array &$data, $key) {
		$this->log("Creating signature using '{$key}'");
		if (!$key || !is_string($key) || $key === '' || !$data || !is_array($data)) {
				return null;
		}

		ksort($data);

		// Create the URL encoded signature string
		$ret = http_build_query($data, '', '&');

		// Normalise all line endings (CRNL|NLCR|NL|CR) to just NL (%0A)
		$ret = preg_replace('/%0D%0A|%0A%0D|%0A|%0D/i', '%0A', $ret);
		// Hash the signature string and the key together
		$hash = hash('SHA512', $ret . $key);
		$this->log("Signature: {$hash}");
		return $hash;
	}
	/**
	 * Check whether an array contains the keys specified
	 * @param  Array  $dict  The array to check keys for
	 * @param  Array  $keys   The array of keys to check
	 * @return boolean        The result of the search
	 */
	public function hasKeys($dict, $keys) {
		$this->log('Checking for keys');

		$missingKeys = array();

		foreach ($keys as $key) {
			if (!array_key_exists($key, $dict)) {
				array_push($missingKeys, $key);
			}
		}

		$missing = count($missingKeys) > 0;
		if ($missing) {
			$this->log(
				sprintf(
					'Missing keys were discovered: %s in %s',
					json_encode($missingKeys),
					json_encode(array_keys($dict))
				)
			);
		}
		return !$missing;
	}
	/**
	 * Get the current session
	 * @return Mage_Core_Session ?
	 */
	private function getCoreSession() {
		return Mage::getSingleton('core/session', array('name' => 'frontend'));
	}
	/**
	 * Get checkout session namespace
	 * @return Mage_Checkout_Model_Session
	 */
	public function getCheckout() {
	   return Mage::getSingleton('checkout/session');
	}

	/**
	 * Get current quote
	 * @return Mage_Sales_Model_Quote
	 */
	public function getQuote() {
		return $this->getCheckout()->getQuote();
	}
	/**
	 * Validate an order
	 * @return Cardstream_PaymentGateway_Model_Standard The current instance
	 */
	public function validate() {
		parent::validate();
		return $this;
	}
	/**
	 * Event handler after an order is validated
	 * @param  Mage_Sales_Model_Order_Payment $payment  The payment instance
	 * @return Cardstream_PaymentGateway_Model_Standard The current instance
	 */
	public function onOrderValidate(Mage_Sales_Model_Order_Payment $payment) {
		return $this;
	}
	/**
	 * Event handler after an invoice is created
	 * @param  Mage_Sales_Model_Invoice_Payment $payment The payment instance
	 * @return Cardstream_PaymentGateway_Model_Standard  The current instance
	 */
	public function onInvoiceCreate(Mage_Sales_Model_Invoice_Payment $payment) {
		return $this;
	}
	/**
	 * Redirect URL to go to after placing an order (GET only...)
	 * @return String The redirect URL
	 */
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('PaymentGateway/order/process');
	}
	/**
	 * Builds a readable status from a response
	 * @param  Array $data The response data
	 * @return String      The status
	 */
	private function buildStatus($data) {
		$ordermessage = "Cardstream Payment<br/><br/>" .
			($data['responseCode'] == "0" ? "Payment Successful" : "Payment Unsuccessful") . "<br/><br/>" .
			"Amount Received: " . (isset($data['amountReceived']) ? floatval($data['amountReceived']) / 100 : "None") . "<br/><br/>" .
			"Message: " . $data['responseMessage'] . "<br/>" .
			"xref: " . $data['xref'] . "<br/>" .
			"CV2 Check: " . (isset($data['cv2Check']) ? $data['cv2Check'] : 'Unknown') . "<br/>" .
			"addressCheck: " . (isset($data['addressCheck']) ? $data['addressCheck'] : 'Unknown') . "<br/>" .
			"postcodeCheck: " . (isset($data['postcodeCheck']) ? $data['postcodeCheck'] : 'Unknown') . "<br/>";

		if (isset($data['threeDSEnrolled'])) {
			switch ($data['threeDSEnrolled']) {
				case "Y":
					$enrolledtext = "Enrolled.";
					break;
				case "N":
					$enrolledtext = "Not Enrolled.";
					break;
				case "U";
					$enrolledtext = "Unable To Verify.";
					break;
				case "E":
					$enrolledtext = "Error Verifying Enrolment.";
					break;
				default:
					$enrolledtext = "Integration unable to determine enrolment status.";
					break;
			}
			$ordermessage .= "<br />3D Secure enrolled check outcome: \"$enrolledtext\"";
		}

		if (isset($data['threeDSAuthenticated'])) {
			switch ($data['threeDSAuthenticated']) {
				case "Y":
					$authenticatedtext = "Authentication Successful";
					break;
				case "N":
					$authenticatedtext = "Not Authenticated";
					break;
				case "U";
					$authenticatedtext = "Unable To Authenticate";
					break;
				case "A":
					$authenticatedtext = "Attempted Authentication";
					break;
				case "E":
					$authenticatedtext = "Error Checking Authentication";
					break;
				default:
					$authenticatedtext = "Integration unable to determine authentication status.";
					break;
			}
			$ordermessage .= "<br />3D Secure authenticated check outcome: \"$authenticatedtext\"";
		}
		return $ordermessage;
	}
	/**
	 * Debug to the Mage log and optionally to the error_log
	 * @param  string		$str		The text to write to the log
	 * @param  boolean		$error		Additionally write to the error_log?
	 * @return void
	 */
	public function log($str, $error = false) {
		if ($this->canDebug === true) {
			Mage::Log($str);
			if ($error) {
				error_log($str);
			}
		}
	}

	/**
	 * Obfuscates data for data protection
	 * @return		String 		JSON encoded session data
	 */
	public function commentSessionData() {
		$debugInfo = $this->getSessionData();
		$keys = array(
			'customerName',
			'customerPhone',
			'customerEmail',
			'customerAddress',
			'customerPostCode',
			'transactionUnique',
			'orderRef',
			'amount',
			'merchantID',
		);
		if ($this->method == 'Direct') {
			array_push($keys, 'cardNumber');
			array_push($keys, 'cardStripCode');
			array_push($keys, 'cardExpiryYear');
			array_push($keys, 'cardExpiryMonth');
		}
		$containsEmpty = preg_match('/\"([\w\-]+)\": (?:null|"")/', json_encode($debugInfo), $matches);
		$keysPresent = $this->hasKeys(array_merge($debugInfo['form'], $debugInfo['req']), $keys);
		return (
			$keysPresent && !$containsEmpty ?
			'All expected keys are present' :
			'Not all keys can be validated and issues may occur'
		);
	}
}
?>