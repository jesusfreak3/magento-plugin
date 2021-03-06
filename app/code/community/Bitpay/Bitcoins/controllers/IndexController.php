<?php

// callback controller
class Bitpay_Bitcoins_IndexController extends Mage_Core_Controller_Front_Action {

	public function checkForPaymentAction()
	{
		$params = $this->getRequest()->getParams();
		$quoteId = $params['quote'];
		$paid = Mage::getModel('Bitcoins/ipn')->GetQuotePaid($quoteId);
		print json_encode(array('paid' => $paid));
		exit(); 
	}
	
	// bitpay's IPN lands here
	public function indexAction() {		
		require Mage::getBaseDir('lib').'/bitpay/bp_lib.php';
		
		Mage::log(file_get_contents('php://input'), null, 'bitpay.log');
		
		$apiKey = Mage::getStoreConfig('payment/Bitcoins/api_key');
		$invoice = bpVerifyNotification($apiKey);
		
		if (is_string($invoice))
			Mage::log("bitpay callback error: $invoice", null, 'bitpay.log');
		else {
			// get the order
			if (isset($invoice['posData']['quoteId'])) {
				$quoteId = $invoice['posData']['quoteId'];
				$order = Mage::getModel('sales/order')->load($quoteId, 'quote_id');
			}
			else {
				$orderId = $invoice['posData']['orderId'];
				$order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
			}
			
			// save the ipn so that we can find it when the user clicks "Place Order"
			Mage::getModel('Bitcoins/ipn')->Record($invoice); 
			
			// update the order if it exists already
			if ($order->getId())
				switch($invoice['status']) {
				case 'confirmed':							
				case 'complete':					
					$method = Mage::getModel('Bitcoins/paymentMethod');
					$method->MarkOrderPaid($order);
					
					break;
				}				
		}
	}

}
