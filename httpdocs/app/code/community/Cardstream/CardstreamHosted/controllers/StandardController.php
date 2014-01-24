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

class Cardstream_CardstreamHosted_StandardController extends Mage_Core_Controller_Front_Action {

    /**
    * Get singleton with CardstreamHosted standard order transaction information
    *
    * @return Cardstream_CardstreamHosted_Model_Standard
    */
    public function getStandard() {
    
        return Mage::getSingleton('CardstreamHosted/standard');
        
    }

    /**
    * When a customer chooses Cardstream on Checkout/Payment page
    *
    */
    public function redirectAction() {
    
        $this->getResponse()->setBody($this->getLayout()->createBlock('CardstreamHosted/standard_redirect')->toHtml());
        
    }
    
    /**
    * This is called when the customer returns from payment
    */
    public function successAction() {
                        
        //Grab the database ID
        $collection = Mage::getModel('CardstreamHosted/CardstreamHosted_Trans')->getCollection();
        $collection->addFilter('transactionunique', $_POST['transactionUnique']);
        $transrow = $collection->toArray();
        
        //Grab the database ID
        
        if( isset( $transrow['items'][0]['id'] ) ){
        
            $transid = $transrow['items'][0]['id'];
        
        }else{
            
            $transid = false;
            
        }

        if( $transid ){
            
            //We have a transaction ID
            
            $trans = $transrow['items'][0];
            
            // Get the last four of the card used.
            if( isset( $_POST['cardNumberMask'] ) ){
            
                $lastfour = substr($_POST['cardNumberMask'], -4, strlen($_POST['cardNumberMask']) );
            
            }else{
                
                $lastfour = false;
                
            }
            
            //If threeDSEnrolled has been sent, insert it into the database. Otherwise, insert nothing.
            
            if( isset( $_POST['threeDSEnrolled'] ) ){
                
                $threeDSEnrolled = $_POST['threeDSEnrolled'];
                
            }else{
                
                $threeDSEnrolled = false;
                
            }
            
            //If threeDSAuthenticated has been sent, insert it into the database. Otherwise, insert nothing.
            
            if( isset( $_POST['threeDSAuthenticated'] ) ){
                
                $threeDSAuthenticated = $_POST['threeDSAuthenticated'];
                
            }else{
                
                $threeDSAuthenticated = false;
                
            }
            
            //If cardType has been sent, insert it into the database. Otherwise, insert nothing.
            if( isset( $_POST['cardType'] ) ){
                
                $cardType = $_POST['cardType'];
                
            }else{
                
                $cardType = false;
                
            }
            
            //Update the database with the transaction result
            $trn = Mage::getModel('CardstreamHosted/CardstreamHosted_Trans')->loadById( $trans['id'] );
            $trn->setxref( $_POST['xref'] )
            ->setresponsecode( $_POST['responseCode'] )
            ->setmessage( $_POST['responseMessage'] )
            ->setthreedsenrolled( $threeDSEnrolled )
            ->setthreedsauthenticated( $threeDSAuthenticated )
            ->setlastfour( $lastfour )
            ->setcardtype( $cardType )
            ->save();

            if( $_POST['responseCode'] === "0") {

                if( $_POST['amountReceived'] == $trans['amount'] ){

                    //Load order
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId( $trans['orderid'] );

                    if( $order->getId() ){

                        $order->sendNewOrderEmail();
                        $order->true;

                        if( !$order->canInvoice() ) {

                            //Add order comment and update status - Although we cant invoice, its important to record the transaction outcome.
                            $order->addStatusToHistory( Mage::getStoreConfig('payment/CardstreamHosted_standard/successfulpaymentstatus'), $this->buildordermessage(), 0);
                            $order->save();

                            //when order cannot create invoice, need to have some logic to take care
                            $order->addStatusToHistory(
                                        $order->getStatus(),
                                        Mage::helper('CardstreamHosted')->__('Order cannot create invoice')
                            );

                        }else{

                            //need to save transaction id
                            $order->getPayment()->setTransactionId( $_POST['xref'] );
                            $order->save();
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

                            //Add order comment and update status.
                            $order->addStatusToHistory( Mage::getStoreConfig('payment/CardstreamHosted_standard/successfulpaymentstatus'), $this->buildordermessage(), 0);

                            $order->save();

                        }

                    }

                    $this->_redirect('checkout/onepage/success');

                }else{

                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId( $trans['orderid'] );

                    if( $order->getId() ){

                        //Add order comment and update status
                        $order->addStatusToHistory( Mage::getStoreConfig('payment/CardstreamHosted_standard/order_status'), $this->buildordermessage(), 0);
                        $order->addStatusToHistory( Mage::getStoreConfig('payment/CardstreamHosted_standard/order_status'), "The amount paid did not match the amount due.", 0);

                        $order->save();

                    }

                    $session = Mage::getSingleton('checkout/session');
                    $session->setQuoteId( $trans['quoteid'] );
                    Mage::getModel('sales/quote')->load($session->getQuoteId())->setIsActive(true)->save();

                    $message = "The amount paid did not match the amount due. Please contact us for more information";
                    $session->addError($message);
                    $this->_redirect('checkout/cart');

                }
                    
            }else{
                    
                $order = Mage::getModel('sales/order');
                $order->loadByIncrementId( $trans['orderid'] );

                if( $order->getId() ){

                    //Add order comment and update status.
                    $order->addStatusToHistory( Mage::getStoreConfig('payment/CardstreamHosted_standard/unsuccessfulpaymentstatus'), $this->buildordermessage(), 0);
                    $order->save(); 

                }

                $session = Mage::getSingleton('checkout/session');
                $session->setQuoteId( $trans['quoteid'] );
                Mage::getModel('sales/quote')->load($session->getQuoteId())->setIsActive(true)->save();

                $this->loadLayout();

                $block = $this->getLayout()->createBlock(
                    'Mage_Core_Block_Template',
                    'CardstreamHosted/standard_failure',
                    array('template' => 'CardstreamHosted/standard/failure.phtml')
                );

                $this->getLayout()->getBlock('content')->append($block);

                $this->renderLayout();
                    
            }
            
        }else{
            
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId( Mage::getSingleton('checkout/session')->getCardstreamHostedOrderId() );

            if( $order->getId() ){

                //Add order comment and update status.
                $order->addStatusToHistory( Mage::getStoreConfig('payment/CardstreamHosted_standard/order_status'), $this->buildordermessage(), 0);
                $order->addStatusToHistory( Mage::getStoreConfig('payment/CardstreamHosted_standard/order_status'), "Unable to locate the transaction in the CardstreamHosted table", 0);
                $order->save(); 

            }
            
            $session = Mage::getSingleton('checkout/session');
            $session->setQuoteId( Mage::getSingleton('checkout/session')->getCardstreamHostedQuoteId() );
            Mage::getModel('sales/quote')->load($session->getQuoteId())->setIsActive(true)->save();
            
            $message = "Unable to locate transaction. Please contact us for payment status.";
            $session->addError($message);
            $this->_redirect('checkout/cart');
            
        }
        
    }
    
    function buildordermessage() {
        
        if( $_POST['responseCode'] === "0" ){
            $paymentoutcome = "Payment Successful";
        }else{
            $paymentoutcome = "Payment Unsuccessful";
        }
        
        if( isset( $_POST['amountReceived'] ) ){
        
            $amountreceived = number_format( round( ( $_POST['amountReceived'] / 100 ) , 2 ), 2);
        
        }else{
            
            $amountreceived = "Unknown";
            
        }
        
        $ordermessage = "Cardstream Payment<br /><br />" . $paymentoutcome . "<br /><br />Amount Received: " . $amountreceived . "<br />Message: \"" . ucfirst( $_POST['responseMessage'] ) . "\"<br />Xref: " . $_POST['xref'];

        if( isset( $_POST['cv2Check'] ) ){

            $ordermessage .= "<br />CV2 Check Result: " . ucfirst( $_POST['cv2Check'] );

        }

        if( isset( $_POST['addressCheck'] ) ){

            $ordermessage .= "<br />Address Check Result: " . ucfirst( $_POST['addressCheck'] );

        }

        if( isset( $_POST['postcodeCheck'] ) ){

            $ordermessage .= "<br />Postcode Check Result: " . ucfirst( $_POST['postcodeCheck'] );

        }

        if( isset( $_POST['threeDSEnrolled'] ) ){
        
            switch( $_POST['threeDSEnrolled'] ){
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

            $ordermessage .= "<br />3D Secure enrolled check outcome: \"" . $enrolledtext . "\"";
        
        }

        if( isset( $_POST['threeDSAuthenticated'] ) ){

            switch( $_POST['threeDSAuthenticated'] ){
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

            $ordermessage .= "<br />3D Secure authenticated check outcome: \"" . $authenticatedtext . "\"";
        
        }
        
        return $ordermessage;
        
    }
	
}