<?php

class Gateway extends GatewayCore {
	//bixie
	public $order_id;
	public $order_code;
	public $rPluginParams;
	public $zoo;

	// Load iDEAL settings
	public function __construct ($settingsData = array(), $rPluginParams) {
		$this->init($settingsData);
		$this->rPluginParams = $rPluginParams;
	}


	// Setup payment
	public function doSetup () {
		global $aIdealCheckout;

		$sHtml = '';

		// Look for proper GET's en POST's
		if (!isset($this->order_id) || !isset($this->order_code)) {
			$sHtml .= '<p>Invalid issuer request.</p>';
		} else {
			//bixie
			$sOrderId = $this->order_id;
			$sOrderCode = $this->order_code;


			// Lookup transaction
			if ($this->getRecordByOrder($sOrderId, $sOrderCode)) {
				if (strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
					$sHtml .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_ALREADY_COMPLETE');
				} elseif ((strcmp($this->oRecord['transaction_status'], 'OPEN') === 0) && !empty($this->oRecord['transaction_url'])) {
					header('Location: ' . $this->oRecord['transaction_url']);
					exit;
				} else {
					try {
						$mollie = new iDEAL_Payment();
						if (!empty($this->aSettings['TEST_MODE'])) {
							$mollie->setApiKey($this->aSettings['API_KEY_TEST']);
						} else {
							$mollie->setApiKey($this->aSettings['API_KEY']);
						}

						$issuers = $mollie->getBanks();
						$actionUrl = $this->zoo->zoocart->payment->getCallbackUrl('ideal', 'html') . '&transaction=1&order_id=' . $sOrderId . '&order_code=' . $sOrderCode;


						if ($issuers === false) {
							$sHtml .= '<code>Er is een fout opgetreden bij het ophalen van de banklijst: ' . $mollie->getErrorMessage() . '</code>';
						}

						if (empty($this->oRecord['transaction_log']) == false) {
							$this->oRecord['transaction_log'] .= "\n\n";
						}

						$this->oRecord['transaction_log'] .= 'Executing IssuerRequest on ' . date('Y-m-d, H:i:s') . '.';

						$this->save();

						$sIssuerList = '';
						foreach ($issuers as $issuer) {
							if ($issuer->method == Mollie_API_Object_Method::IDEAL) {
								$sIssuerList .= '<option value=' . htmlspecialchars($issuer->id) . '>' . htmlspecialchars($issuer->name) . '</option>';
							}
						}

						$sHtml .= '
	<div class="uk-margin">
		<form action="' . htmlspecialchars($actionUrl) . '" method="post" id="checkout" class="uk-form uk-form-horizontal">
			<div class="uk-form-formrow">
				<label class="uk-form-label">' . JText::_('PLG_ZOOCART_PAYMENT_IDEAL_CHOOSE_BANK') . '</label>
				<div class="uk-form-controls">
					<select name="issuer_id">' . $sIssuerList . '</select>
				</div>
			</div>
			<div class="zoocart-checkout-buttons uk-nbfc">
				<button class="uk-button uk-button-success uk-float-right">
					<i class="uk-icon-shopping-cart uk-margin-small-right"></i>' . JText::_('PLG_ZOOCART_CHECKOUT') . '
				</button>
			</div>
		</form>
	</div>
	';
					} catch (Mollie_API_Exception $e) {
						$sHtml .= '<p>'. $e->getMessage() .'</p>';
					}

				}
			} else {
				$sHtml .= '<p>Invalid issuer request.</p>';
			}
		}

		return $sHtml;
	}


	// Execute payment
	public function doTransaction () {
		$sHtml = '';

		// Look for proper GET's en POST's
		if (empty($_POST['issuer_id']) || empty($_GET['order_id']) || empty($_GET['order_code'])) {
			$sHtml .= '<p>Invalid transaction request.</p>';
		} else {
			$sIssuerId = $_POST['issuer_id'];

			// Lookup transaction
			if ($this->getRecordByOrder()) {
				if (strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
					$sHtml .= '<p>Transaction already completed</p>';
				} elseif ((strcmp($this->oRecord['transaction_status'], 'OPEN') === 0) && !empty($this->oRecord['transaction_url'])) {
					header('Location: ' . $this->oRecord['transaction_url']);
					exit;
				} else {
					try {
						$mollie = new iDEAL_Payment();
						if (!empty($this->aSettings['TEST_MODE'])) {
							$mollie->setApiKey($this->aSettings['API_KEY_TEST']);
						} else {
							$mollie->setApiKey($this->aSettings['API_KEY']);
						}
						$returnUrl = $this->zoo->zoocart->payment->getCallbackUrl('ideal') . '&order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code'];

						$payment = $mollie->createPayment(
							$this->oRecord['order_id'],
							$this->oRecord['order_code'],
							$this->oRecord['transaction_amount'],
							$this->oRecord['transaction_description'],
							$returnUrl,
							$sIssuerId
						);

						$sTransactionId = $payment->id;
						$sTransactionUrl = $payment->getPaymentUrl();

						if (empty($this->oRecord['transaction_log']) == false) {
							$this->oRecord['transaction_log'] .= "\n\n";
						}

						$this->oRecord['transaction_log'] .= 'Executing TransactionRequest on ' . date('Y-m-d, H:i:s') . '. Recieved: ' . $sTransactionId . ': ' . $payment->status;
						$this->oRecord['transaction_id'] = $sTransactionId;
						$this->oRecord['transaction_url'] = $sTransactionUrl;
						$this->oRecord['transaction_status'] = 'OPEN';
						$this->oRecord['transaction_date'] = time();

						$this->save();

						// idealcheckout_die('<a href="' . htmlentities($sTransactionUrl) . '">' . htmlentities($sTransactionUrl) . '</a>', __FILE__, __LINE__);
						header('Location: ' . $sTransactionUrl);
						exit;
					} catch (Mollie_API_Exception $e) {
						$sHtml .= '<p>'. $e->getMessage() .'</p>';
					}
				}
			} else {
				$sHtml .= '<p>Invalid transaction request.</p>';
			}
		}

		idealcheckout_output($sHtml);
	}


	// Catch return
	public function doReturn () {
		$returnResult = array(
			'valid' => false,
			'order_id' => 0,
			'transaction_id' => '',
			'transaction_date' => '',
			'status' => '',
			'message' => '',
			'success' => 0,
			'redirect' => false,
			'messageStyle' => '',
			'formHtml' => ''
		);
		if (empty($_GET['order_id']) || empty($_GET['order_code'])) {
			$returnResult['valid'] = false;
			$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
			$returnResult['messageStyle'] = 'uk-alert-danger';
			$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
		} else {
			// Lookup transaction
			if ($this->getRecordByOrder($_GET['order_id'], $_GET['order_code'])) {
				$returnResult['order_id'] =  $this->oRecord['order_id'];
				$returnResult['valid'] = true;
				$returnResult['transaction_id'] = $this->oRecord['transaction_id'];
				// Transaction already finished by webhook
				if (strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
					$returnResult['success'] = JPaymentDriver::ZC_PAYMENT_PAYED;
					$returnResult['status'] = 'SUCCESS';
					$returnResult['debug'] .= $returnResult['message'] = 'al afgehandeld';
					if ($this->oRecord['transaction_success_url']) {
						$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
					}
					return $returnResult;
				} else {

					try {
						$mollie = new iDEAL_Payment();
						if (!empty($this->aSettings['TEST_MODE'])) {
							$mollie->setApiKey($this->aSettings['API_KEY_TEST']);
						} else {
							$mollie->setApiKey($this->aSettings['API_KEY']);
						}
						$payment = $mollie->checkPayment($this->oRecord['transaction_id']);
						if ($payment->isPaid() == true) {
							$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_SUCCESS');
							$returnResult['messageStyle'] = 'uk-alert-success';
							$returnResult['success'] = JPaymentDriver::ZC_PAYMENT_PAYED;
							$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
						} elseif ($payment->isOpen() == false) {
							$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_FAILED');
							$returnResult['messageStyle'] = 'uk-alert-danger';
							$returnResult['success'] = JPaymentDriver::ZC_PAYMENT_FAILED;
							$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];
						}
					} catch (Mollie_API_Exception $e) {
						$returnResult['valid'] = false;
						$returnResult['message'] = $e->getMessage();
						$returnResult['messageStyle'] = 'uk-alert-danger';
						$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
					}

					if (empty($this->oRecord['transaction_log']) == false) {
						$this->oRecord['transaction_log'] .= "\n\n";
					}

					$this->oRecord['transaction_log'] .= 'Return from Mollie on ' . date('Y-m-d, H:i:s') . ' for #' . $this->oRecord['transaction_id'] . '. Recieved: ' . $this->oRecord['transaction_status'];

					$this->save();
				}

			} else {
				$returnResult['valid'] = false;
				$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
				$returnResult['messageStyle'] = 'uk-alert-danger';
				$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
			}
		}

		return $returnResult;
	}


	// Catch report
	public function doReport () {
		$returnResult = array(
			'valid' => false,
			'order_id' => 0,
			'transaction_id' => '',
			'transaction_date' => '',
			'status' => '',
			'message' => '',
			'success' => 0,
			'redirect' => false,
			'messageStyle' => '',
			'formHtml' => ''
		);

		if (empty($_GET['id'])) {
			$returnResult['valid'] = false;
			$returnResult['message'] = 'Invalid return request.';
			$returnResult['redirect'] = false;
		} else {
			$sTransactionId = $_GET['id'];
			$returnResult['valid'] = true;
			try {
				$mollie = new iDEAL_Payment();
				if (!empty($this->aSettings['TEST_MODE'])) {
					$mollie->setApiKey($this->aSettings['API_KEY_TEST']);
				} else {
					$mollie->setApiKey($this->aSettings['API_KEY']);
				}
				$payment = $mollie->checkPayment($sTransactionId);
				$this->order_id = $payment->metadata->order_id;
				$this->order_code = $payment->metadata->order_code	;

				// Lookup record
				if ($this->getRecordByOrder()) {

					if ($payment->isPaid() == true) {
						$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_SUCCESS');
						$returnResult['messageStyle'] = 'uk-alert-success';
						$returnResult['success'] = 1;
						$this->oRecord['transaction_status'] = 'SUCCESS';
						$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
					} elseif ($payment->isOpen() == false) {
						$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_FAILED');
						$returnResult['messageStyle'] = 'uk-alert-danger';
						$returnResult['success'] = 0;
						$this->oRecord['transaction_status'] = 'FAILURE';
						$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];
					}

					if (empty($this->oRecord['transaction_log']) == false) {
						$this->oRecord['transaction_log'] .= "\n\n";
					}

					$this->oRecord['transaction_log'] .= 'Push from Mollie on ' . date('Y-m-d, H:i:s') . ' for #' . $sTransactionId . '. Recieved: ' . $this->oRecord['transaction_status'];

					$this->save();

					$returnResult['status'] = $this->oRecord['transaction_status'];
					$returnResult['message'] = 'De transactie status is bijgewerkt.';
				} else {
					$returnResult['message'] = 'Invalid report request.';
				}
			} catch (Mollie_API_Exception $e) {
				$returnResult['valid'] = false;
				$returnResult['message'] = $e->getMessage();
				$returnResult['messageStyle'] = 'uk-alert-danger';
				$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
			}

		}

		return $returnResult;
	}


	// Validate all open transactions
	public function doValidate () {
	}
}
