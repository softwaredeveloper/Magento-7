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
 * @package    Hosted
 * @copyright  Copyright (c) 2009 - 2012 Cardstream Limited (http://www.cardstream.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
 
class Cardstream_CardstreamHosted_Model_Standard extends Mage_Payment_Model_Method_Abstract {

    //changing the payment to different from cc payment type and Cardstream payment type
    const PAYMENT_TYPE_AUTH = 'AUTHORIZATION';
    const PAYMENT_TYPE_SALE = 'SALE';
    
    protected $_code  = 'CardstreamHosted_standard';
    protected $_canUseInternal = true;
    protected $_canCapture = true;
    protected $_canUseForMultishipping  = false;
    protected $_formBlockType = 'CardstreamHosted/standard_form';

    /**
     * Get CardstreamHosted session namespace
     *
     * @return Cardstream_CardstreamHosted_Model_Session
     */
    public function getSession() {
        
        return Mage::getSingleton('CardstreamHosted/session');
        
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout() {
        
        return Mage::getSingleton('checkout/session');
        
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote() {
        
        return $this->getCheckout()->getQuote();
        
    }

    /**
     * Using internal pages for input payment data
     *
     * @return bool
     */
    public function canUseInternal() {
        
        return true;
        
    }

    /**
     * Using for multiple shipping address
     *
     * @return bool
     */
    public function canUseForMultishipping() {
        
        return true;
        
    }

    public function createFormBlock($name) {
        
        $block = $this->getLayout()->createBlock('CardstreamHosted/standard_form', $name)
        ->setMethod('CardstreamHosted_standard')
        ->setPayment($this->getPayment())
        ->setTemplate('CardstreamHosted/standard/form.phtml');

        return $block;
        
    }
    
    public function getTransactionId() {
        
        return $this->getSessionData('transaction_id');
        
    }
    
    public function setTransactionId($data) {
        
        return $this->setSessionData('transaction_id', $data);
        
    }
	
    public function validate() {
        
        parent::validate();		
        return $this;
        
    }
    
    public function onOrderValidate(Mage_Sales_Model_Order_Payment $payment) {
        
       return $this;
       
    }
    
    public function onInvoiceCreate(Mage_Sales_Model_Invoice_Payment $payment) {
        
		return $this;
        
    }
    
    public function canCapture() {
        
        return true;
        
    }
    
    public function getOrderPlaceRedirectUrl() {
        
          return Mage::getUrl('CardstreamHosted/standard/redirect');
          
    }

    public function getStandardCheckoutFormFields() {

        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        $billingAddress = $order->getBillingAddress();
        $customer = Mage::getSingleton('customer/session')->getCustomer();
               
        //Format the order amount for the gateway.
        $amount = (int) round ( ( $order->getBaseTotalDue() * 100 ) );
        
        //Determin the payment action
        if( $this->getConfigData('payment_action') == "AUTHORIZATION" ) {
            
                $action = "PREAUTH";
                
        }else{
            
                $action = "SALE";
                
        }

        //Gather Country and Currency Data
        $countryCode = $this->getConfigData('CountryCode');
        $currencyCode = $this->getConfigData('CurrencyCode');

        if( !$countryCode ){
            
                $countryCode = "826";
                
        }

        if( !$currencyCode ){
            
                $currencyCode = "826";
                
        }
        
        //Build the customer address string
        $customerAddress = "";
        
        if( $billingAddress->getStreet(1) ){
            
            $customerAddress .= $billingAddress->getStreet(1) . "\n";
            
        }
        
        if( $billingAddress->getStreet(2) ){
            
            $customerAddress .= $billingAddress->getStreet(2) . "\n";
            
        }
                
        if( $billingAddress->getCity() ){
            
            $customerAddress .= $billingAddress->getCity() . "\n";
            
        }
        
        if( $billingAddress->getRegion() ){
            
            $customerAddress .= $billingAddress->getRegion() . "\n";
            
        }
        
        if( $billingAddress->getCountry() ){
            
            $customerAddress .= $billingAddress->getCountry() . "\n";
            
        }
        
        //Remove trailing line break.
        $customerAddress = rtrim($customerAddress);
        
        //Build the customer name
        $customerName = rtrim( $customer->getFirstname() . " " . $customer->getLastname()  );
                
        //Get the redirectURL
        $redirectURL = Mage::getUrl("CardstreamHosted/standard/success/");
        
        //Generate the transactionUnique value
        $transactionUnique = uniqid("", true) . uniqid("", true) . uniqid("", true);
                
        //Save to DB            
        Mage::getModel('CardstreamHosted/CardstreamHosted_Trans')
        ->setcustomerid( Mage::getSingleton('customer/session')->getCustomer()->getId() )
        ->settransactionunique( $transactionUnique )
        ->setorderid( $orderIncrementId )
        ->setctime( NOW() )
        ->setamount( $amount )
        ->setip( $_SERVER['REMOTE_ADDR'] )
        ->setquoteid( Mage::getSingleton('checkout/session')->getQuoteId() )
        ->save();
        
        //Set this so we can restore the customers cart should payment fail.
        Mage::getSingleton('checkout/session')->setCardstreamHostedQuoteId( Mage::getSingleton('checkout/session')->getQuoteId() );
        
        //Set this so we know the order ID in the redirectURL page
        Mage::getSingleton('checkout/session')->setCardstreamHostedOrderId( $orderIncrementId );
        
        $paymentData = array(
            'merchantID' => $this->getConfigData('MerchantID'),    
            'amount' => $amount,
            'action' => $action,
            'type' => 1,
            'countryCode' => $countryCode,
            'currencyCode' => $currencyCode,
            'transactionUnique' => $transactionUnique,
            'orderRef' => $orderIncrementId,
            'redirectURL' => $redirectURL,
            'customerName' => $customerName,
            "customerAddress" => $customerAddress,
            "customerPostCode" => $billingAddress->getPostcode(),
            "customerEmail" => $order->getCustomerEmail(),
            "customerPhone" => $billingAddress->getTelephone()
        );


		
        //URL encode the paymentData values, as these are dynamically generated and could break the $_GET string.
      //  foreach( $paymentData as $key => &$value ){
                        
      //          $value = htmlentities( $value );
            
      //  }

		ksort($paymentData);
		$paymentData['signature'] = hash('SHA512', http_build_query($paymentData).$this->getConfigData('MerchantSharedKey')) . '|' . implode(',', array_keys($paymentData));
        //Add order comment and update status.
        $order->addStatusToHistory( Mage::getStoreConfig('payment/CardstreamHosted_standard/order_status'), "Customer beginning payment process", 0);
        $order->save();
        
        return $paymentData;    
        
    }
    
    public function getCardstreamHostedUrl() {

        return "https://gateway.cardstream.com/hosted/";
	
    }
    
}
