<?php
/**
* @package		ZOOcart
* @author		ZOOlanders http://www.zoolanders.com
* @author		Matthijs Alles - Bixie
* @copyright	Copyright (C) JOOlanders, SL
* @license		http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

class plgZoocart_PaymentIdeal extends JPaymentDriver {

	public function __construct(&$subject, $config = array()) {
		parent::__construct($subject, $config);

		$this->app->path->register(dirname(__FILE__),'ideal');
		$this->app->path->register(dirname(__FILE__).'/idealcheckout','idealcheckout');
		$this->app->path->register(dirname(__FILE__).'/idealcheckout/gateways','idealgateways');
		// register classes
		if ( $path = $this->app->path->path( 'ideal:classes' ) ) {
			$this->app->path->register($path, 'classes');
			$this->app->loader->register('Idealcheckout', 'classes:idealcheckout.php');
		}
		// register tables
		if ( $path = $this->app->path->path( 'ideal:tables' ) ) {
			$this->app->path->register($path, 'tables');
			$this->app->loader->register('IdealcheckoutTable', 'tables:idealcheckout.php');
		}

	}

	public function getPaymentFee($data = array()) {
		if ($this->params->get('fee_type' ,'net') == 'perc') {
			$perc = ((float) $this->params->get('fee', 0)) / 100;

			if ($data['order']) {
				return $data['order']->net * $perc;
			} else {
				return $this->app->zoocart->cart->getSubtotal($this->app->user->get()->id) * $perc;
			}
		}

		return (float) $this->params->get('fee', 0);
	}

	protected function getRenderData($data = array()) {
		$data = parent::getRenderData($data);
		//get the gatewaysettings
		$idealType = $this->params->get('type', 'ideal-simulator');
		$gatewaySettings = $this->_getGatewaySettings($idealType);
		//init idealcheckout after gatewaysettings!
		require_once($this->app->path->path('idealcheckout:includes/init.php'));
		//sort data
		$data['test'] = $this->params->get('test', 0);
		$data['auto'] = $this->params->get('auto', 0);
		$billing_address_id = $this->app->data->create($data['order']->billing_address)->id;
		$billing_address = $this->app->zoocart->table->addresses->get((int)$billing_address_id);
		$user = JFactory::getUser($data['order']->user_id);
		$amount = $data['order']->total;
		$order_code = idealcheckout_getRandomCode(32);
		$description = JText::_('PLG_ZOOCART_ORDER') . ' ' . $data['order']->id . ', ' . JFactory::getApplication()->getCfg('sitename');
		$aOrderParams['contact'] = array(
			'first_name' => '', 
			'last_name' => $billing_address->name, 
			'address1' => $billing_address->address, 
			'address2' => '', 
			'zip' => $billing_address->zip, 
			'city' =>$billing_address->city, 
			'country' => $billing_address->country, 
			'email' => $user->email, 
			'phone' => '', 
		);
		$aOrderParams['status'] = array(
			'success' => 'COMPLETED',
			'pending' => 'PENDING',
			'cancelled' => 'FAILED',
		);
// $this->app->zoocart->payment->getCallbackUrl('ideal');
		//create checkout object
		$idealdata = $this->app->data->create();
		$idealdata['order_id'] = $data['order']->id;
		$idealdata['order_code'] = $order_code;
		$idealdata['order_params'] = serialize($aOrderParams);
		$idealdata['store_code'] = idealcheckout_getStoreCode();
		$idealdata['gateway_code'] = 'ideal';
		$idealdata['language_code'] = 'nl';
		$idealdata['country_code'] = null;
		$idealdata['currency_code'] = 'EUR';
		$idealdata['transaction_id'] = idealcheckout_getRandomCode(32);
		$idealdata['transaction_code'] = idealcheckout_getRandomCode(32);
		$idealdata['transaction_params'] = null;
		$idealdata['transaction_date'] = JHtml::_('date','now','U');
		$idealdata['transaction_amount'] = $amount;
		$idealdata['transaction_status'] = null;
		$idealdata['transaction_description'] = $description;
		$idealdata['transaction_url'] = null;
		$idealdata['transaction_payment_url'] = $this->app->zoocart->payment->getReturnUrl();
		$idealdata['transaction_success_url'] = $this->app->zoocart->payment->getReturnUrl();
		$idealdata['transaction_pending_url'] = $this->app->zoocart->payment->getCancelUrl();
		$idealdata['transaction_failure_url'] = $this->app->zoocart->payment->getCancelUrl();
		$idealdata['transaction_log'] = null;
		//save to database
		if (!$gatewaySettings || !$this->app->zoocart->table->idealcheckout->save($idealdata)) {
			//then what? Can I throw/return error?
		}
		//get the gateway
		$oGateway = new Gateway($gatewaySettings,$this->params);
		$oGateway->order_id = $idealdata['order_id'];
		$oGateway->order_code = $idealdata['order_code'];
		$oGateway->zoo = $this->app;
		$data['formHtml'] = $oGateway->doSetup();

		return $data;
	}

	public function message($data = array()) {
		$html = '';
		$app = App::getInstance('zoo');
		$message = $this->app->session->get('com_zoo.zoocart.payment_ideal.message','');
		$messageStyle = $this->app->session->get('com_zoo.zoocart.payment_ideal.messageStyle','');
		$formHtml = $this->app->session->get('com_zoo.zoocart.payment_ideal.formHtml','');
		if ($message || $formHtml) {
			if ($message) $html .= '<div class="uk-alert uk-alert-large '.$messageStyle.'" data-uk-alert><a href="" class="uk-alert-close uk-close"></a>'.$message.'</div>';
			if ($formHtml) $html .= '<div class="uk-form">'.$formHtml.'</div>';
			$this->app->session->set('com_zoo.zoocart.payment_ideal.message',null);
			$this->app->session->set('com_zoo.zoocart.payment_ideal.messageStyle',null);
			$this->app->session->set('com_zoo.zoocart.payment_ideal.formHtml',null);
		}
		return $html;
	}

	public function render($data = array()) {
		$app = App::getInstance('zoo');
		$data['order']->state = $app->zoocart->getConfig()->get('payment_pending_orderstate', 4);
		$app->zoocart->table->orders->save($data['order']);

		return parent::render($data);
	}
	
	/**
	 * Plugin event triggered when the payment plugin notifies for the transaction
	 *
	 * @param  array  $data The data received
	 *
	 * @return array(
	 *         		status: 0 => failed, 1 => success, -1 => pending
	 *         		transaction_id
	 *         		order_id,
	 *         		total,
					redirect: false (default) or internal url
	 *         )
	 */
	public function callback($data = array()) {
		$data = $this->app->data->create($data);
		//get the gatewaysettings
		$idealType = $this->params->get('type', 'ideal-simulator');
		$gatewaySettings = $this->_getGatewaySettings($idealType);
		//init idealcheckout after gatewaysettings!
		require_once($this->app->path->path('idealcheckout:includes/init.php'));
		$oGateway = new Gateway($gatewaySettings,$this->params);
		$oGateway->zoo = $this->app;
		//type of callback
		if (JRequest::getInt('push',0)) {
			$returnResult = $oGateway->doReport();
		} elseif (JRequest::getInt('transaction',0)) {
			$returnResult = $oGateway->doTransaction();
		} else {
			$returnResult = $oGateway->doReturn();
		}
		$this->app->session->set('com_zoo.zoocart.payment_ideal.message',$returnResult['message']);
		$this->app->session->set('com_zoo.zoocart.payment_ideal.messageStyle',$returnResult['messageStyle']);
		$this->app->session->set('com_zoo.zoocart.payment_ideal.formHtml',$returnResult['formHtml']);
		//	echo $oGateway->doValidate(); //TODO lookup transactions in admin?

		$id = (int) $returnResult['order_id'];
		if($id) {
			$order = $this->app->zoocart->table->orders->get($id);
		} else {
			$order = $data->get('order', null);
		}
		$status = 0;
		// Checked against frauds in gateway
		$valid = $returnResult['valid'];
		
		if ($valid) {
			$valid = $order->id > 0;
			// todo: check multiple crossing payments
			if (!$valid) {
				$status = 0;
			} else {
				//get the payment_status
				$status = $returnResult['success'];
			}
			return array('status' => $status, 'transaction_id' => $returnResult['transaction_id'], 'order_id' => $order->id, 'total' => $order->total,'redirect'=>$returnResult['redirect']);
		}
		//add a redirect option here
		return array('status' => 0, 'transaction_id' => $returnResult['transaction_id'], 'order_id' => $order->id, 'total' => 0,'redirect'=>$returnResult['redirect']);
	}
	
	/**
	 * Get gateway settings
	 *
	 * @param string $idealType
	 * @return mixed false || JSONData object gatewaysettings
	 */
	protected function _getGatewaySettings ($idealType) {
		if ($file = $this->app->path->path('idealgateways:'.$idealType.'/config.json')) {
			$gatewaySettings = $this->app->data->create(file_get_contents($file));
			foreach (array('id1','id2','key1','key2','key3','test') as $settingKey) {
				if ($setting = $gatewaySettings->get($settingKey,false)) {
					$gatewaySettings->set($setting['key'],$this->params->get($settingKey,null));
					if ($gatewaySettings->get($setting['key'],null) === null) {
						$gatewaySettings->set($setting['key'],@$setting['default']);
					}
				}
			}
			return $gatewaySettings;
		}
		return false;
	}

}