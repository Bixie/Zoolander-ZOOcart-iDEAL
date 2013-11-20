<?php

	class Gateway extends GatewayCore
	{
		//bixie
		public $order_id;
		public $order_code;
		public $rPluginParams;
		public $zoo;
		// Load iDEAL settings
		public function __construct($settingsData=array())
		{
			$this->init($settingsData);
		}
		
		// Setup payment
		public function doSetup()
		{
			global $aIdealCheckout;

			$sHtml = '';

			// Look for proper GET's en POST's
			if(!isset($this->order_id) || !isset($this->order_code)) //bixie
			{
				$sHtml .= '<p>Invalid transaction request.</p>';
			}
			else
			{
				//bixie
				$sOrderId = $this->order_id;
				$sOrderCode = $this->order_code;

				// Lookup transaction
				if($this->getRecordByOrder($sOrderId,$sOrderCode)) //bixie
				{
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
					{
						$sHtml .= '<p>Transaction already completed</p>';
					}
					elseif((strcmp($this->oRecord['transaction_status'], 'OPEN') === 0) && !empty($this->oRecord['transaction_url']))
					{
						//header('Location: ' . $this->oRecord['transaction_url']);
						//exit;
					}
					else
					{
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
	<button class="btn btn-success btn-large" type="submit">'.JText::_('PLG_ZOOCART_PAYMENT_IDEAL_BUTTONTEXT').'</button>
</form>';

						$sHtml = $sFormHtml;

						if($this->rPluginParams->get('auto'))
						{
							$sHtml .= "<script type=\"text/javascript\">jQuery(document).ready(function($){
	$('#zoocart-ideal button[type=\"submit\"]').trigger('click');
})
 </script>";
						}
					}
				}
				else
				{
					$sHtml .= '<p>Invalid transaction request.</p>';
				}
			}

			return $sHtml;
		}


		// Catch return
		public function doReturn()
		{
			$returnResult = array('response'=>array(),'redirect'=>false);
			$returnResult['request'] = JRequest::get('GET');
			$returnResult['debug'] = "Ideal Simulator \n";
			$returnResult['valid'] = false;

			if(empty($_GET['transaction_id']) || empty($_GET['transaction_code']) || empty($_GET['status']))
			{
				$returnResult['succes'] = false;
				$returnResult['message'] = 'Invalid return request.';
				$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
			}
			else
			{
				$sTransactionId = $_GET['transaction_id'];
				$sTransactionCode = $_GET['transaction_code'];
				$sTransactionStatus = $_GET['status'];

				// Lookup record
				if($this->getRecordByTransaction())
				{
					$returnResult['order_id'] = $this->oRecord['order_id'];
					if(strcasecmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
					{
						$returnResult['succes'] = false;
						$returnResult['status'] = 'SUCCESS';
						$returnResult['debug'] .= $returnResult['message'] = 'al afgehandeld';
						$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
					}
					else
					{
						
						$returnResult['response']['transactionId'] = $sTransactionId;
						$returnResult['response']['transactionCode'] = $sTransactionCode;
						$returnResult['response']['transactionStatus'] = $sTransactionStatus;


						$statusName = '';
						switch ($sTransactionStatus) {
							case 'SUCCESS':
								$statusName = 'SUCCESS';
							break;
							case 'FAILURE':
								$statusName = 'FAILURE';
							break;
							case 'CANCELLED':
								$statusName = 'CANCELLED';
							break;
							case 'PENDING':
								$statusName = 'PENDING';
							break;
						}

						$returnResult['succes'] = true;
						$returnResult['transaction_id'] = $sTransactionId;
						$returnResult['debug'] .= "BetaalID: ".$sTransactionId." \n";
						$returnResult['status'] = $statusName;
						$returnResult['debug'] .= "Status: $statusName \n";
						$returnResult['transaction_date'] = strftime('%Y-%m-%d %T',time());
						
						$this->oRecord['transaction_status'] = $sTransactionStatus;

						if(empty($this->oRecord['transaction_log']) == false)
						{
							$this->oRecord['transaction_log'] .= "\n\n";
						}

						$this->oRecord['transaction_log'] .= 'Executing StatusRequest on ' . date('Y-m-d, H:i:s') . ' for #' . $this->oRecord['transaction_id'] . '. Recieved: ' . $this->oRecord['transaction_status'];

						$this->save();


						
						// Set status message
						if(strcasecmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
						{
							$returnResult['valid'] = true;
						}
						else
						{
							if(strcasecmp($this->oRecord['transaction_status'], 'CANCELLED') === 0){
								$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];
							}
						}

						if($this->oRecord['transaction_success_url'] && (strcasecmp($this->oRecord['transaction_status'], 'SUCCESS') === 0))
						{
							$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
						}
					}
				}
				else
				{
					$returnResult['debug'] .= $returnResult['message'] = 'Invalid return request.';
					$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
				}
			}

			return $returnResult;

		}
	}

?>