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
namespace Cardstream\PaymentGateway\Controller\Order;

class Process extends \Magento\Framework\App\Action\Action {
	private $method, $session, $instance;
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
		\Cardstream\PaymentGateway\Model\Gateway $model
	) {
		$this->resultPageFactory = $resultPageFactory;
		$this->instance = $model;
		$this->method = $this->instance->integrationType;
		$this->session = $this->instance->getSessionData();
		parent::__construct($context);
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
	private function handle($result) {
		if (isset($result['error']) && $result['error'] !== null) {
			$this->messageManager->addError($result['error']);
		}
		if (isset($result['redirect']) && $result['redirect'] !== null) {
			$this->_redirect($result['redirect']);
		}
	}
	public function execute() {
		$resultRedirect = $this->resultRedirectFactory->create();
		$this->instance->log(json_encode($this->session));
		if ($this->method == 'hosted' && $this->isPaymentSubmission()) {

			$this->instance->redirectPayment();

		} else if ($this->method == 'direct' && $this->isPaymentSubmission()) {

			$this->handle($this->instance->sendDirectPayment($resultRedirect));

		} else if ($this->method == 'hosted' && $this->isPaymentResponse()) {

			$this->handle($this->instance->processAll($_POST, $resultRedirect));

		} else {
			$this->instance->log(
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
			$this->handle(array(
				'error' => sprintf($responseError, $invalidRequest),
				'redirect' => 'checkout/cart'
			));
		}

	}

}
