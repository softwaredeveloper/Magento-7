<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Cardstream
 * @package    PaymentGateway
 * @copyright  Copyright (c) 2017 Cardstream
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Cardstream\PaymentGateway\Model;

class Gateway extends \Magento\Payment\Model\Method\AbstractMethod {
	const _MODULE = 'Cardstream_PaymentGateway';
	const DIRECT_URL = 'https://gateway.cardstream.com/direct/';
	const HOSTED_URL = 'https://gateway.cardstream.com/hosted/';
	const VERIFY_ERROR = 'The signature provided in the response does not match. This response might be fraudulent';
	const PROCESS_ERROR = 'Sorry, we are unable to process this order (reason: %s). Please correct any faults and try again.';
	const SERVICE_ERROR = 'SERVICE ERROR - CONTACT ADMIN';
	const INVALID_REQUEST = 'INVALID REQUEST';
	const INSECURE_ERROR = 'The %s module cannot be used under an insecure host and has been hidden for user protection';

	protected $_code = self::_MODULE;

	protected $_countryFactory;
	protected $_checkoutSession;

	protected $_isGateway              = true;
	protected $_canCapture             = true;
	protected $_canUseInternal         = true;
	protected $_canUseCheckout         = true;
	protected $_canCapturePartial      = true;
	protected $_isInitializeNeeded     = true;
	protected $_canUseForMultishipping = true;
	protected $_canSendNewEmailFlag    = false;
	protected $_canSaveCc              = false;
	protected $_canRefund              = false;
	protected $_canVoid                = false;

	private $merchantId, $secret, $debug, $redirectURL;

	public $integrationType, $responsive, $countryCode, $currencyCode, $session;

	public function __construct(
		\Magento\Framework\UrlInterface $urlBuilder,
		\Magento\Framework\Exception\LocalizedExceptionFactory $exception,
		\Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
		\Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
		\Magento\Sales\Model\OrderFactory $orderFactory,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\Model\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
		\Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
		\Magento\Payment\Helper\Data $paymentData,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Payment\Model\Method\Logger $logger,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
		\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
		array $data = []
	) {
		$this->_urlBuilder = $urlBuilder;
		$this->_exception = $exception;
		$this->_transactionRepository = $transactionRepository;
		$this->_transactionBuilder = $transactionBuilder;
		$this->_checkoutSession = $checkoutSession;
		$this->_orderFactory = $orderFactory;
		$this->_storeManager = $storeManager;
		$this->_isScopePrivate = true;
		$this->session = &$checkoutSession;

		parent::__construct(
			$context,
			$registry,
			$extensionFactory,
			$customAttributeFactory,
			$paymentData,
			$scopeConfig,
			$logger,
			$resource,
			$resourceCollection,
			$data
		);
		$this->merchantId = $this->getConfigData('merchant_id');
		$this->secret = $this->getConfigData('merchant_shared_key');
		$this->integrationType = $this->getConfigData('integration_type');
		$this->responsive = $this->getConfigData('form_responsive') ? 'Y' : 'N';
		$this->countryCode = $this->getConfigData('country_code');
		$this->currencyCode = $this->getConfigData('currency_code');
		$this->log = (boolean)$this->getConfigData('debug');

		if (!$this->isSecure() && $this->integrationType == 'direct') {
			$this->_canUseCheckout = false;
			$error = sprintf(self::INSECURE_ERROR, self::_MODULE);
			$this->log($error, true);
		}
		// Tell our template to load the integration type we need
		setcookie(self::_MODULE . "_IntegrationMethod", $this->integrationType);
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
	public function assignData(\Magento\Framework\DataObject $data) {
		$this->log('Assigning form data for checkout');
		// Get the current active session
		$session = $this->session->getData();
		// Clear any data inside
		if (isset($session[self::_MODULE])) {
			unset($session[self::_MODULE]['form']);
		}
		// Get all the payment data from the form
		$paymentData = $data->getData();
		// Set the payment form data to the session
		$session[self::_MODULE]['form'] = $paymentData['additional_data'];
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
	 * own array block inside the session known as _MODULE.
	 * Making sure to seperate this information and the form
	 * submitted by the user. We can then merge the data
	 * effortlessly
	 *
	 * @return void
	 */
	public function captureOrder() {
		$this->log('Building order request for payment');
		// Prevent any email getting sent
		$order = $this->_checkoutSession->getLastRealOrder();
		$order->setEmailSent(0);
		$order->setStatus($this->getConfigData('order_status'));
		$order->save();

		$orderId = $order->getIncrementId();
		$amount = round($order->getBaseTotalDue(), 2) * 100;
		$ref = '#' . $orderId;
		$billingAddress = $order->getBillingAddress();

		// Create a formatted address
		$address = ($billingAddress->getStreetLine(1) ? $billingAddress->getStreetLine(1) . ",\n" : '');
		$address .= ($billingAddress->getStreetLine(2) ? $billingAddress->getStreetLine(2) . ",\n" : '');
		$address .= ($billingAddress->getCity() ? $billingAddress->getCity() . ",\n" : '');
		$address .= ($billingAddress->getRegion() ? $billingAddress->getRegion() . ",\n" : '');
		$address .= ($billingAddress->getCountry() ? $billingAddress->getCountry() : '');

		$req = array(
			'merchantID'        => $this->merchantId,
			'amount'            => $amount,
			'transactionUnique' => uniqid(),
			'orderRef'          => $ref,
			'countryCode'       => $this->countryCode,
			'currencyCode'      => $this->currencyCode,
			'customerName'      => $billingAddress->getName(),
			'customerAddress'   => $address,
			//'customerPostCode'  => $billingAddress->getPostcode(),
			'customerEmail'     => $billingAddress->getEmail(),
			'remoteAddress'     => $_SERVER['REMOTE_ADDR']
		);

		if ($this->integrationType == "direct") {
			$req['action'] = 'SALE';
			$req['type'] = 1;
		}

		if(!is_null($billingAddress->getTelephone())) {
			$req["customerPhone"] = $billingAddress->getTelephone();
		}

		if (!is_null($billingAddress->getPostcode())) {
			// PostCode's are optional.
			$req['customerPostCode'] = $billingAddress->getPostcode();
		}

		return $req;
	}

	/**
	 * Redirect the user to the hosted form using the captured order information
	 * @return void
	 */
	public function redirectPayment() {
		$this->log('Redirecting user for payment');
		$session = $this->getSessionData();
		$processURL = $this->getOrderPlaceRedirectUrl();
		$req = array_merge(
			$this->captureOrder(),
			array(
				'redirectURL' => $processURL,
				'callbackURL' => $processURL,
				'formResponsive' => $this->responsive
			)
		);

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
		$required = array('cardNumber', 'cardCVV', 'cardExpiryYear', 'cardExpiryMonth');
		$req = array();
		foreach ($required as $index=>$key) {
			if (isset($session['form'][$key])) {
				$req[$key] = $session['form'][$key];
			} else {
				$this->log("Missing key '{$key}' from checkout form", true);
				$error = sprintf(self::PROCESS_ERROR, 'MISSING ' . strtoupper($key));
				return array(
					'error' => $error,
					'redirect' => 'checkout/cart'
				);
			}
		}
		return $req;
	}
	/**
	 * Setup a direct payment and send it to the right place
	 * @return void
	 */
	public function sendDirectPayment() {
		// Try to process a direct payment
		$session = $this->getSessionData();
		$formData = $this->getDirectDetails();
		if (isset($formData['error'])) {
			// Redirect when direct card details are missing
			return $formData;
		}

		$req = array_merge(
			$this->captureOrder(),
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

		$req['signature'] = $this->createSignature($req, $this->secret);
		$res = $this->makeRequest(self::DIRECT_URL, $req);
		$this->log('Verifying response from gateway');
		if (!$this->hasKeys($res, $this->getGenuineResponseHeaders())) {
			$this->log('Unable to verify response as one or more keys are missing');
			$this->clearData();
			return array(
				'error' => sprintf(self::PROCESS_ERROR, self::INVALID_REQUEST),
				'redirect' => 'checkout/cart'
			);
		}
		return $this->processAll($res);
	}


	/**
	 * Process a payment response and
	 * @param  array		$data		The response from the payment gateway
	 * @return void
	 */
	public function processAll($data) {
		$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
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
				$orderId = str_replace('#', '', $data['orderRef']);
				$order = $this->_orderFactory->create();
				$order->loadByIncrementId($orderId);

				// Process a successful transaction
				if ($data['responseCode'] == 0){
					$this->log('Payment successful - ammending order details');
					// Save payment information
					$amount = intval($data['amountReceived']) / 100;
					$payment = $order->getPayment();
					// Set order status
					$status = $this->getConfigData('successful_status');
					if($order->getStatus() != $status) {
						$order->setBaseTotalPaid($amount)->setTotalPaid($amount);
						$objectManager->create('Cardstream\PaymentGateway\Model\Trans')
							->setCustomerid($order->getCustomerId())
							->setTransactionunique($data['transactionUnique'])
							->setOrderid($orderId)
							->setAmount( $data['amountReceived'] )
							->setIp($_SERVER['REMOTE_ADDR'])
							->setQuoteid($order->getQuoteId())
							->save();
						// Create invoice if we can
						$order->setStatus($status);
						$order->addStatusToHistory(
							$status,
							$this->buildStatus($data),
							0
						);
						$orderSender = $objectManager->create('\Magento\Sales\Model\Order\Email\Sender\OrderSender');
						if ($order->canInvoice()) {
							$invoice = $order->prepareInvoice();
							if ($invoice->getTotalQty()) {
								$invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
								$invoice->register();
								$transactionSave = $objectManager->create('\Magento\Framework\DB\Transaction')
									->addObject($invoice)
									->addObject($invoice->getOrder());
								$transactionSave->save();
								$invoice->save();
							}
						}
						$orderSender->send($order);
						$order->save();
					}
					$this->clearData();
					return array(
						'error' => null,
						'redirect' => 'checkout/onepage/success'
					);
				} else {
					if ($order) {
						$this->log('Payment was unsuccessful - ' . $data['responseMessage']);
						$status = $this->getConfigData('unsuccessful_status');
						$order->setStatus($status);
						$order->addStatusToHistory(
							$status,
							$this->buildStatus($data),
							0
						);
						$payment = $order->getPayment();
						$objectManager->create('Cardstream\PaymentGateway\Model\Trans')
							->setCustomerid($order->getCustomerId())
							->setTransactionunique($data['transactionUnique'])
							->setOrderid($orderId)
							->setAmount(null)
							->setIp($_SERVER['REMOTE_ADDR'])
							->setQuoteid($order->getQuoteId())
							->save();
						try {
							$order->cancel();
						} catch (Exception $e){
							$this->log($e, true);
						}
						// Remove order increment ID since it's still going to generated by Magento
						$order->setIncrementId(null);
						$quote = $objectManager->create('\Magento\Quote\Model\Quote');
						$quoteId = $order->getQuoteId();
						$quote->loadByIdWithoutStore($quoteId);
						$quote->setReserveredOrderId(null);
						$quote->setIsActive(true);
						$quote->save();
						$order->save();
						$this->session->setQuoteId($quoteId);
					}
					$this->clearData();
					$error = sprintf(self::PROCESS_ERROR, htmlentities($data['responseMessage']));
					return array(
						'error' => $error,
						'redirect' => 'checkout/cart'
					);
				}
			}
		} else {
			// Never trust the data when the signature cannot be verified
			$this->clearData();
			$this->log('Signature could not be verified');
			$error = self::VERIFY_ERROR;
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

	public function log($message, $error = false) {
		// Keep dummy as an optional value so that we can use this function instead (hopefully)
		$this->_logger->addDebug($message);
		if ($error) {
			$this->_logger->addError($message);
		}
	}
	public function getOrderPlaceRedirectUrl() {
		return $this->_urlBuilder->getUrl('cardstream/order/process');
	}
}
