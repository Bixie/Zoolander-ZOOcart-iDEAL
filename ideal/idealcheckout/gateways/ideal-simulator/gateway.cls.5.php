<?php

	class Gateway extends GatewayCore
	{
		//bixie
		public $order_id;
		public $order_code;
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
						$uri = JURI::getInstance();
						$prot = BixTools::config('algemeen.betalen.bix_ideal.useSecure',1)?'https://':'http://';
						$siteRoot = $prot.$uri->toString(array('host', 'port'));
						$returnUrl = $siteRoot.'/index.php?option=com_bixprintshop&task=cart.betaalreturn';
						$sFormHtml = '
<form action="https://www.ideal-checkout.nl/payment/" method="post">
	<input name="gateway_code" type="hidden" value="ideal">
	<input name="order_id" type="hidden" value="' . htmlspecialchars($this->oRecord['order_id']) . '">
	<input name="order_description" type="hidden" value="' . htmlspecialchars($this->oRecord['transaction_description']) . '">
	<input name="order_amount" type="hidden" value="' . htmlspecialchars($this->oRecord['transaction_amount']) . '">
	<input name="url_cancel" type="hidden" value="' . $returnUrl . '&transaction_id=' . $this->oRecord['transaction_id'] . '&transaction_code=' . $this->oRecord['transaction_code'] . '&status=CANCELLED">
	<input name="url_success" type="hidden" value="' . $returnUrl . '&transaction_id=' . $this->oRecord['transaction_id'] . '&transaction_code=' . $this->oRecord['transaction_code'] . '&status=SUCCESS">
	<input name="url_error" type="hidden" value="' . $returnUrl . '&transaction_id=' . $this->oRecord['transaction_id'] . '&transaction_code=' . $this->oRecord['transaction_code'] . '&status=FAILURE">
	<input type="submit" value="Verder >>">
</form>';

						$sHtml = $sFormHtml;

						if($this->aSettings['TEST_MODE'] == false)
						{
							//$sHtml .= '<script type="text/javascript"> function doAutoSubmit() { document.forms[0].submit(); } setTimeout(\'doAutoSubmit()\', 100); </script>';
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
			$returnResult = array('response'=>array());
			$returnResult['request'] = JRequest::get('GET');
			$returnResult['debugEmail'] = "Ideal Simulator \n";

			if(empty($_GET['transaction_id']) || empty($_GET['transaction_code']) || empty($_GET['status']))
			{
				$returnResult['succes'] = false;
				$returnResult['message'] = 'Invalid return request.';
			}
			else
			{
				$sTransactionId = $_GET['transaction_id'];
				$sTransactionCode = $_GET['transaction_code'];
				$sTransactionStatus = $_GET['status'];

				// Lookup record
				if($this->getRecordByTransaction())
				{
					if(strcasecmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
					{
						$returnResult['succes'] = false;
						$returnResult['betaalStatus'] = 'BIX_SUCCESS';
						$returnResult['debugEmail'] .= $returnResult['message'] = 'al afgehandeld';
							// ??
					}
					else
					{
						
						$returnResult['response']['transactionId'] = $sTransactionId;
						$returnResult['response']['transactionCode'] = $sTransactionCode;
						$returnResult['response']['transactionStatus'] = $sTransactionStatus;


						$statusName = '';
						switch ($sTransactionStatus) {
							case 'SUCCESS':
								$statusName = 'BIX_SUCCESS';
							break;
							case 'FAILURE':
								$statusName = 'BIX_FAILURE';
							break;
							case 'CANCELLED':
								$statusName = 'BIX_CANCELLED';
							break;
							case 'PENDING':
								$statusName = 'BIX_PENDING';
							break;
						}

						$returnResult['succes'] = true;
						$returnResult['betaalID'] = $sTransactionId;
						$returnResult['debugEmail'] .= "BetaalID: ".$sTransactionId." \n";
						$returnResult['betaalStatus'] = $statusName;
						$returnResult['debugEmail'] .= "Status: $statusName \n";
						$returnResult['betaalDatum'] = strftime('%Y-%m-%d %T',time());
						
						$this->oRecord['transaction_status'] = $sTransactionStatus;

						if(empty($this->oRecord['transaction_log']) == false)
						{
							$this->oRecord['transaction_log'] .= "\n\n";
						}

						$this->oRecord['transaction_log'] .= 'Executing StatusRequest on ' . date('Y-m-d, H:i:s') . ' for #' . $this->oRecord['transaction_id'] . '. Recieved: ' . $this->oRecord['transaction_status'];

						$this->save();



						// Handle status change
						if(function_exists('idealcheckout_update_order_status'))
						{
							//idealcheckout_update_order_status($this->oRecord, 'doReturn');
						}


						
						// Set status message
						if(strcasecmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
						{
							$returnResult['valid'] = true;
							//$sHtml .= '<p>Uw betaling is met succes ontvangen.<br><input style="margin: 6px;" type="button" value="Terug naar de website" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl(1)) . '\'"></p>';
						}
						else
						{
							if(strcasecmp($this->oRecord['transaction_status'], 'CANCELLED') === 0){
							}
						}

						if($this->oRecord['transaction_success_url'] && (strcasecmp($this->oRecord['transaction_status'], 'SUCCESS') === 0))
						{
							//header('Location: ' . $this->oRecord['transaction_success_url']);
							//exit;
						}
					}
				}
				else
				{
					$returnResult['debugEmail'] .= $returnResult['message'] = 'Invalid return request.';
				}
			}

			return $returnResult;

		}
	}

?>