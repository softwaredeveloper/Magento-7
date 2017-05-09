<?php

define('MODULE_PAYMENT_CARDSTREAM_VERIFY_ERROR', "The signature provided in the response does not match. This response might be fraudulent");
define('MODULE_PAYMENT_CARDSTREAM_RESPONSE_ERROR', "Sorry, we are unable to process this order (reason: %s). Please correct any faults and try again.");
define('MODULE_PAYMENT_CARDSTREAM_DIRECT_URL','https://gateway.cardstream.com/direct/');
define('MODULE_PAYMENT_CARDSTREAM_FORM_URL', 'https://gateway.cardstream.com/hosted/');
class Cardstream_PaymentGateway_Model_Standard extends Mage_Payment_Model_Method_Abstract {

    protected $_code  = 'PaymentGateway_standard';
    protected $_formBlockType;
    protected $_isGateway               = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;
    protected $_isInitializeNeeded      = true;
    public $method, $session;
    public function __construct(){
        $this->method = $this->getConfigData('IntegrationMethod');
        $this->_formBlockType = "PaymentGateway/{$this->method}Form";
        $this->_infoBlockType = "PaymentGateway/{$this->method}Form";
        $this->countryCode = $this->getConfigData('CountryCode');
        $this->currencyCode = $this->getConfigData('CurrencyCode');
        $this->secret = $this->getConfigData('MerchantSharedKey');
        $this->merchantID = $this->getConfigData('MerchantID');
        $this->responsive = "N"; //TODO: Add responsive option
        $this->session = $this->getCoreSession();

        if(!$this->isSecure() && $this->method == 'Direct') {
            //Log that we need a secure connection
            $error = "The Cardstream PaymentGateway module cannot be used under an insecure host and has been hidden for user protection";
            error_log($error);
            Mage::Log($error);
            $this->_canUseCheckout = false;
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
     * Returns a full list of keys used in any request
     * @return Array request keys
     */
    public function getAllRequestHeaders() {
        //Returns all request keys
        return array(
            'merchantID',
            'signature',
            'amount',
            'transactionUnique',
            'orderRef',
            'customerAddress',
            'customerPostCode',
            'countryCode',
            'currencyCode',
            'redirectURL',
            'callbackURL',
            'customerName',
            'customerEmail',
            'customerPhone',
            'formResponsive',
            'type',
            'cardNumber',
            'cardExpiryMonth',
            'cardExpiryYear',
            'cardCVV',
            'returnInternalData',
            'threeDSMD',
            'threeDSPaRes',
            'threeDSPaReq'
        );
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
     * Returns a list of keys found with genuine requests
     * @return  Array request keys
     */
    public function getGenuineRequestHeaders() {
        return array(
            'amount',
            'currencyCode',
            'countryCode',
            'customerName',
            'customerAddress',
            'customerPostCode',
            'method',//payment method code supplied by magento
            'merchantID',
            'orderRef',
            'transactionUnique'
        );
    }
    /**
     * Get the current milliseconds since epoch
     * @return float   milliseconds since epoch
     */
    public function getCurrentMilliseconds(){
        return round(microtime(true) * 1000);
    }
    /**
     * Check whether the time is within the minutes specified
     * @param  float  $milliseconds  The milliseconds to compare against
     * @param  float  $minutes       Minutes to be within (e.g. 1 minute)
     * @return boolean               The result
     */
    public function isWithinMinutes($milliseconds, $minutes) {
        return ($milliseconds - $this->getCurrentMilliseconds()) < (($minutes * 60) * 1000);
    }
    /**
     * Save any assigned POST['payment'] data to our session as we can't get it any other way :(
     * Our payment data is then stored as named input fields (e.g. payment[merchantID])
     * MAKE SURE TO REMOVE ALL PAYMENT INFORMATION AFTERWARDS (using $this->clearData();)
     * @param  Varien_Object $data POST DATA
     * @return NULL
     */
    public function assignData($data){
        //Merge current session data
        $this->session->setData(array_merge($this->session->getData(), $data->getData()));
        $this->session->setCTime($this->getCurrentMilliseconds());
    }
    /**
     * Remove ALL payment session information
     */
    public function clearData() {
        //Remove all payment session data
        $session = $this->getCoreSession();
        $keys = $this->getAllRequestHeaders();
        $save = array();
        foreach($session->getData() as $key=>$value){
            if (!in_array($key, $keys)) {
                $save[$key] = $value;
            }
        }
        unset($save['CTime']); //Remove session timeout limit
        //Replace session without any payment data
        $session->setData($save);
    }
    /**
     * Creates the basis request for Hosted and Direct integrations
     * @return Array (request data)
     */
    public function createGeneralRequest(){
        $quote = $this->getQuote();
        $amount = $quote->getTotals()['grand_total']->getValue() * 100;
        $ref = $quote->getUpdatedAt() . " - " . $quote->getId();
        $billingAddress = $quote->getBillingAddress();
        //Create a formatted address
        $address = ($billingAddress->getStreet(1) ? $billingAddress->getStreet(1) . "\n" : '');
        $address .= ($billingAddress->getStreet(2) ? $billingAddress->getStreet(2) . "\n" : '');
        $address .= ($billingAddress->getCity() ? $billingAddress->getCity() . "\n" : '');
        $address .= ($billingAddress->getRegion() ? $billingAddress->getRegion() . "\n" : '');
        $address .= ($billingAddress->getCountry() ? $billingAddress->getCountry() : '');
        $req = array(
            "merchantID"        => $this->merchantID,
            "amount"            => $amount,
            "transactionUnique" => uniqid(),
            "orderRef"          => $ref,
            "countryCode"       => $this->countryCode,
            "currencyCode"      => $this->currencyCode,
            "customerName"      => $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
            "customerAddress"   => $address,
            "customerPostCode"  => $billingAddress->getPostcode(),
            "customerEmail"     => $billingAddress->getEmail(),
            "remoteAddress" 	=> $_SERVER['REMOTE_ADDR']
        );

        if(!is_null($billingAddress->getTelephone())) {
            $req["customerPhone"] = $billingAddress->getTelephone();
        }

        return $req;
    }
    /**
     * Creates a hosted request array for POSTing to the Cardstream payment gateway
     * @return Array (payment form data)
     */
    public function createHostedRequest() {
        $redirectURL = $this->getOrderPlaceRedirectUrl();
        $req = array_merge(
            $this->createGeneralRequest(),
            array(
                "redirectURL"       => $redirectURL,
                "callbackURL"       => $redirectURL,
                "formResponsive"    => $this->responsive
            )
        );
        $req['signature'] = $this->createSignature($req, $this->secret);
        return $req;
    }
    /**
     * Creates a direct request array for POSTing to a Cardstream payment gateway privately
     * @return Array (payment form data)
     */
    public function createDirectRequest() {
	$session = $this->getCoreSession();
        $req = array_merge(
            $this->createGeneralRequest(),
            array_filter(array(
                "action"             => "SALE",
                "type"               => 1,
                "cardNumber"         => $session->getCardstreamNumber(),
                "cardExpiryMonth"    => $session->getCardstreamExpiryMonth(),
                "cardExpiryYear"     => $session->getCardstreamExpiryYear(),
                "cardCVV"            => $session->getCardstreamStripCode(),
                "customerName"       => $session->getCardstreamName(),
             	"customerAddress"    => $session->getCardstreamAddress(),
            	"customerPostCode"   => $session->getCardstreamPostcode(),
            	"customerEmail"      => $session->getCardstreamEmail(),
        		"threeDSMD"          => (isset($_REQUEST['MD']) ? $_REQUEST['MD'] : null),
                "threeDSPaRes"       => (isset($_REQUEST['PaRes']) ? $_REQUEST['PaRes'] : null),
                "threeDSPaReq"       => (isset($_REQUEST['PaReq']) ? $_REQUEST['PaReq'] : null)
            ))
     	);

        if(!is_null($session->getCardstreamPhone())) {
            $req["customerPhone"] = $session->getCardstreamPhone();
        }
	
        return $req;
    }
    public function retrieveSpecificKeys($array, $keys){
        $specific = array();
        foreach ($array as $key => $value) {
            if (in_array($key, $keys)) {
                $specific[$key] = $value;
            }
        }
        return $specific;
    }
    public function redirectPayment() {
        //Instead of creating a whole new hosted request
        //just retrieve the keys to return the saved session hosted request
        echo "<form id='redirect' action='" . MODULE_PAYMENT_CARDSTREAM_FORM_URL . "' method='POST'>";
        //Get session stored keys for a hosted request
        $data = $this->retrieveSpecificKeys($this->session->getData(), array_keys($this->createHostedRequest()));
        foreach ($data as $key => $value) {
            echo "<input type='hidden' name='$key' value='$value'/>";
        }
        echo "</form>";
        echo "<script>document.onreadystatechange = () => {document.getElementById('redirect').submit();}</script>";
    }
    public function processAll($data) {
    //This is a valid response to save payment details
        $sig = isset($data['signature']) ? $data['signature'] : null;
        unset($data['signature']);
        if ($sig === $this->createSignature($data, $this->secret)) {
            if ($data['responseCode'] == 65802) {
                //3D Secure process must complete
                $pageUrl = ($this->isSecure() ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . $_SERVER["REQUEST_URI"];
                die("
                    <script>document.onreadystatechange = function() { document.getElementById('3ds').submit(); }</script>
                    <form id='3ds' action=\"" . htmlentities($data['threeDSACSURL']) . "\" method=\"post\">
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
                //Process a successful transaction
                if ($data['responseCode'] == 0){
                    //Save payment information
                    $amount = floatval($data['amountReceived']) / 100;
                    $payment = $order->getPayment();
                    //Set order status
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
                        //Create invoice if we can
                        if ($order->canInvoice) {
                            $converter = Mage::getModel('sales/convert_order');
                            $invoice = $converter->toInvoice($order);

                            foreach($order->getAllItems() as $orderItem) {

                                if(!$orderItem->getQtyToInvoice()) {
                                    continue;
                                }

                                $item = $converter->itemToInvoiceItem($orderItem);
                                $item->setQty($orderItem->getQtyToInvoice());
                                $invoice->addItem($item);
                            }

                            $invoice->collectTotals();
                            $invoice->register()->capture();
                            $CommentData = "Invoice " . $invoice->getIncrementId() . " was created";
                            Mage::getModel('core/resource_transaction')
                            ->addObject($invoice)
                            ->addObject($invoice->getOrder())
                            ->save();
                        }
                        $order->setStatus($status);
                        $order->addStatusToHistory(
                            $status,
                            $this->buildStatus($data),
                            0
                        );
                        $order->sendNewOrderEmail();
                        $order->save();
                    }
                    $this->clearData();
                    Mage::app()->getResponse()->setRedirect(Mage::getUrl("checkout/onepage/success"));
                } else {
                    if ($order) {
                        $status = Mage::getStoreConfig('payment/PaymentGateway_standard/unsuccessfulpaymentstatus');
                        $order->setStatus($status);
                        $order->addStatusToHistory(
                            $status,
                            $this->buildStatus($data),
                            0
                        );
                        $order->save();
                        try {
                            $order->delete();
                        } catch (Exception $e) {
                            /*
                             * This will most likely be deleted because the callback
                             * did this. However it could be that we just can't
                             * delete it. However, it will have the unsuccessful
                             * status anyhow
                             */
                        }
                    }
                    $this->clearData();
                    $quote->setIsActive(true)->save();
                    $error = sprintf(MODULE_PAYMENT_CARDSTREAM_RESPONSE_ERROR, htmlentities($data['responseMessage']));
                    $this->session->addError($error);
                    Mage::app()->getResponse()->setRedirect(Mage::getUrl("checkout/cart"));
                }
            }
        } else {
            $this->clearData();
            if ($data['responseCode'] !== 0) {
                $error = sprintf(MODULE_PAYMENT_CARDSTREAM_RESPONSE_ERROR, $data['responseCode'] . ' - ' . $data['responseMessage']);
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
                    //It may not exist yet
                }
            } else {
                $error = MODULE_PAYMENT_CARDSTREAM_VERIFY_ERROR;
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
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($req));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        parse_str(curl_exec($ch), $res);
        curl_close($ch);
        return $res;
    }
    /**
     * Sign requests with a SHA512 hash
     * @param  Array  $data Request data
     * @param  String $key  The secret to use as part of the signature
     * @return String       The finished signature
     */
    public function createSignature(array &$data, $key) {
        if (!$key || !is_string($key) || $key === '' || !$data || !is_array($data)) {
                return null;
        }

        ksort($data);

        // Create the URL encoded signature string
        $ret = http_build_query($data, '', '&');

        // Normalise all line endings (CRNL|NLCR|NL|CR) to just NL (%0A)
        $ret = preg_replace('/%0D%0A|%0A%0D|%0A|%0D/i', '%0A', $ret);
        // Hash the signature string and the key together
        return hash('SHA512', $ret . $key);
    }
    /**
     * Check whether an array contains the keys specified
     * @param  Array  $array  The array to check keys for
     * @param  Array  $keys   The array of keys to check
     * @return boolean        The result of the search
     */
    public static function hasKeys($array, $keys) {
        $checkKeys = array_keys($array);
        $str = '';
        foreach ($keys as $key){
            if(!array_key_exists($key, $array)) {
                return false;
            }
        }
        return true;
    }
    /**
     * Get the current session
     * @return Mage_Core_Session ?
     */
    public function getCoreSession() {
        return Mage::getSingleton('core/session', array('name' => 'frontend'));
    }
    /**
     * Get the current PaymentGateway session
     * @return [type] [description]
     */
    public function getSession() {
        return Mage::getSingleton('PaymentGateway/session');
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
}
