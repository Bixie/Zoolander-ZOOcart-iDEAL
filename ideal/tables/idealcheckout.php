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

class IdealcheckoutTable extends AppTable {

	public function __construct($app) {
		parent::__construct($app, '#__zoo_zl_zoocart_idealcheckout', 'id');

		$this->app->loader->register('Ideal', 'classes:ideal.php');
		$this->class = 'Idealcheckout';
	}

	public function save($object) {

		$object->created_on = $this->app->date->create('now', $this->app->date->getOffset())->toSql();

		return parent::save($object);
	}

	public function getByOrder($order_id) {
		return $this->all(array('conditions' => 'order_id = ' . (int) $order_id));
	}
}

class IdealcheckoutTableException extends AppTableException {}