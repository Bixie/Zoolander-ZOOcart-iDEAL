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
		if (!isset($this->order_id) || !isset($this->order_code)) //bixie
		{
			$sHtml .= '<p>Invalid transaction request.</p>';
		} else {
			//bixie
			$sOrderId = $this->order_id;
			$sOrderCode = $this->order_code;

			// Lookup transaction
			if ($this->getRecordByOrder($sOrderId, $sOrderCode)) //bixie
			{
				if (strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
					$sHtml .= '<p>Transaction already completed</p>';
				} elseif ((strcmp($this->oRecord['transaction_status'], 'OPEN') === 0) && !empty($this->oRecord['transaction_url'])) {
					//header('Location: ' . $this->oRecord['transaction_url']);
					//exit;
				} else {
					// Set return URLs //bixie
					$returnUrl = $this->zoo->zoocart->payment->getCallbackUrl('ideal');
					$sFormHtml = '
<form action="https://www.ideal-checkout.nl/payment/" method="post" id="zoocart-ideal">
	<input name="gateway_code" type="hidden" value="ideal">
	<input name="order_id" type="hidden" value="' . htmlspecialchars($this->oRecord['order_id']) . '">
	<input name="order_description" type="hidden" value="' . htmlspecialchars($this->oRecord['transaction_description']) . '">
	<input name="order_amount" type="hidden" value="' . htmlspecialchars($this->oRecord['transaction_amount']) . '">
	<input name="url_cancel" type="hidden" value="' . $returnUrl . '&transaction_id=' . $this->oRecord['transaction_id'] . '&transaction_code=' . $this->oRecord['transaction_code'] . '&status=CANCELLED">
	<input name="url_success" type="hidden" value="' . $returnUrl . '&transaction_id=' . $this->oRecord['transaction_id'] . '&transaction_code=' . $this->oRecord['transaction_code'] . '&status=SUCCESS">
	<input name="url_error" type="hidden" value="' . $returnUrl . '&transaction_id=' . $this->oRecord['transaction_id'] . '&transaction_code=' . $this->oRecord['transaction_code'] . '&status=FAILURE">
	<button class="btn btn-success btn-large" type="submit">' . JText::_('PLG_ZOOCART_PAYMENT_IDEAL_BUTTONTEXT') . '</button>
</form>';

					$sHtml = $sFormHtml;

					if ($this->rPluginParams->get('auto')) {
						$sHtml .= "<script type=\"text/javascript\">jQuery(document).ready(function($){
	$('#zoocart-ideal button[type=\"submit\"]').trigger('click');
})
 </script>";
					}
				}
			} else {
				$sHtml .= '<p>Invalid transaction request.</p>';
			}
		}

		return $sHtml;
	}


	// Catch return
	public function doReturn () {
		$returnResult = array(
			'valid'=>false,
			'order_id'=>0,
			'transaction_id'=>'',
			'transaction_date'=>'',
			'status'=>'',
			'message'=>'',
			'success'=>0,
			'redirect'=>false,
			'messageStyle'=>'',
			'formHtml'=>''
		);

		$returnResult['debug'] = "Ideal Simulator \n";
		$returnResult['valid'] = false;

		if (empty($_GET['transaction_id']) || empty($_GET['transaction_code']) || empty($_GET['status'])) {
			$returnResult['success'] = 0;
			$returnResult['message'] = 'Invalid return request.';
			$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
		} else {
			$sTransactionId = $_GET['transaction_id'];
			$sTransactionCode = $_GET['transaction_code'];
			$sTransactionStatus = $_GET['status'];

			// Lookup record
			if ($this->getRecordByTransaction()) {
				$returnResult['order_id'] = $this->oRecord['order_id'];
				if (strcasecmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
					$returnResult['success'] = 1;
					$returnResult['status'] = 'SUCCESS';
					$returnResult['debug'] .= $returnResult['message'] = 'al afgehandeld';
					$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
					return $returnResult;
				} else {

					$returnResult['response']['transactionId'] = $sTransactionId;
					$returnResult['response']['transactionCode'] = $sTransactionCode;
					$returnResult['response']['transactionStatus'] = $sTransactionStatus;


					$statusName = $sTransactionStatus;

					$returnResult['transaction_id'] = $sTransactionId;
					$returnResult['debug'] .= "BetaalID: " . $sTransactionId . " \n";
					$returnResult['status'] = $statusName;
					$returnResult['debug'] .= "Status: $statusName \n";
					$returnResult['transaction_date'] = strftime('%Y-%m-%d %T', time());

					$this->oRecord['transaction_status'] = $sTransactionStatus;

					if (empty($this->oRecord['transaction_log']) == false) {
						$this->oRecord['transaction_log'] .= "\n\n";
					}

					$this->oRecord['transaction_log'] .= 'Executing StatusRequest on ' . date('Y-m-d, H:i:s') . ' for #' . $this->oRecord['transaction_id'] . '. Recieved: ' . $this->oRecord['transaction_status'];

					$this->save();


					switch ($this->oRecord['transaction_status']) {
						case 'SUCCESS':
							$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_SUCCESS');
							$returnResult['messageStyle'] = 'uk-alert-success';
							$returnResult['success'] = 1;
							$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
							break;
						case 'OPEN':
							$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_PENDING');
							$returnResult['messageStyle'] = 'uk-alert-warning';
							$returnResult['success'] = -1;
							$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];
							break;
						case 'CANCELLED':
						case 'EXPIRED':
						case 'FAILURE':
						default:
							$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_FAILED');
							$returnResult['messageStyle'] = 'uk-alert-danger';
							$returnResult['success'] = 0;
							$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];
							break;
					}

				}
			} else {
				$returnResult['debug'] .= $returnResult['message'] = 'Invalid return request.';
				$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
			}
		}

		return $returnResult;

	}
}
