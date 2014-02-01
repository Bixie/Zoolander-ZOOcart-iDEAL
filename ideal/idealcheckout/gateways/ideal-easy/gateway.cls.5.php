<?php

	class Gateway extends GatewayCore
	{
		//bixie
		public $order_id;
		public $order_code;
		public $rPluginParams;
		public $zoo;
		
		// Load iDEAL settings
		public function __construct($settingsData=array(),$rPluginParams) {
			$this->init($settingsData);
			$this->rPluginParams = $rPluginParams;
			//test merchant, default ID
			if ($this->rPluginParams->get('test',0)) {
				$this->aSettings['MERCHANT_ID'] = 'TESTiDEALEASY';
			}
		}

		
		// Setup payment
		public function doSetup() {
			$sHtml = '';

			// Look for proper GET's en POST's
			if(!isset($this->order_id) || !isset($this->order_code)) {//bixie
				$sHtml .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
			} else {
				//bixie
				$sOrderId = $this->order_id;
				$sOrderCode = $this->order_code;

				// Lookup transaction
				if($this->getRecordByOrder($sOrderId,$sOrderCode)) {
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
						$sHtml .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_ALREADY_COMPLETE');
					} else {
						$oIdealEasy = new IdealEasy();
						$returnUrl = $this->zoo->zoocart->payment->getCallbackUrl('ideal','html'). '&order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code'];

						// Set order details
						$oIdealEasy->setMerchant($this->aSettings['MERCHANT_ID']);
						$oIdealEasy->setTestMode(!empty($this->aSettings['TEST_MODE']));
						$oIdealEasy->setAmount($this->oRecord['transaction_amount']); // Bedrag (in EURO's)
						$oIdealEasy->setOrderId($this->oRecord['order_id']); // Unieke order referentie (tot 16 karakters)
						$oIdealEasy->setOrderDescription($this->oRecord['transaction_description']); // Order omschrijving (tot 32 karakters)
						$oIdealEasy->setReturnUrl($returnUrl);
						// Customize submit button
						$oIdealEasy->setButton(JText::_('PLG_ZOOCART_PAYMENT_IDEAL_BUTTONTEXT'));
						
						$sHtml .= $oIdealEasy->createForm();

						if($this->aSettings['TEST_MODE'] === false) {
							//$sHtml .= '<script type="text/javascript"> function doAutoSubmit() { document.forms[checkout].submit(); } setTimeout(\'doAutoSubmit()\', 100); </script>';
						}
					}
				} else {
					$sHtml .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
				}
			}

			return $sHtml;
		}
		
		/**
		 * catch return 
		 * THIS IS NOT A VALID OR SECURE RETURN!!!
		 * CONFIRMATION OF THE PAYMENT CAN ONLY COME FROM THE BANK DIRECTLY VIA DASHBOARD OR MAIL
		 *
		 * @return array(
		 *         		valid: true/false, 
		 *         		transaction_id
		 *         		transaction_date,
		 *         		status,
		 *         		success: 0 => failed, 1 => success, -1 => pending,
		 *				redirect: false (default) or internal url
		 *         )
		 */
		public function doReturn() {
			global $aIdealCheckout; 

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
			
			// Look for proper GET's en POST's
			if(!isset($_GET['order_id']) || !isset($_GET['order_code'])) {
				$returnResult['valid'] = false;
				$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
				$returnResult['messageStyle'] = 'uk-alert-danger';
				$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
			} else {
				//bixie
				$sOrderId = $_GET['order_id'];
				$sOrderCode = $_GET['order_code'];
				// Lookup transaction
				if($this->getRecordByOrder($sOrderId,$sOrderCode)) {
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
						$returnResult['valid'] = true;
						$returnResult['success'] = 1;
						$returnResult['status'] = 'SUCCESS';
						$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_ALREADY_COMPLETE');;
						$returnResult['messageStyle'] = 'uk-alert-success';
						$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
					} else {
						$returnResult['valid'] = true;
						$this->oRecord['transaction_id'] = 'ideal-easy';
						$this->oRecord['transaction_status'] = htmlspecialchars($_GET['status']);
						$returnResult['transaction_id'] = $this->oRecord['transaction_id'];
						$returnResult['status'] = $this->oRecord['transaction_status'];
						$returnResult['transaction_date'] = JHtml::_('date','now','Y-m-d H:i:s');

						if (empty($this->oRecord['transaction_log']) == false) {
							$this->oRecord['transaction_log'] .= "\n\n";
						}

						$this->oRecord['transaction_log'] .= 'RETURN: Recieved status ' . $this->oRecord['transaction_status'] . ' for ' . $this->oRecord['transaction_id'] . ' on ' . date('Y-m-d, H:i:s') . '.';

						switch ($this->oRecord['transaction_status']) {
							case 'SUCCESS':
								$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_SUCCESS');
								$returnResult['messageStyle'] = 'uk-alert-success';
								$returnResult['success'] = 1;
								$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
							break;
							case 'CANCELLED':
							case 'FAILURE':
							default:
								$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_FAILED');
								$returnResult['messageStyle'] = 'uk-alert-danger';
								$returnResult['success'] = 0;
								$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];
							break;
						}

						$this->save();
				
					}
				} else {
					$sHtml .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
				}
			}
			return $returnResult;
		}
	}
