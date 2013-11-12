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

	$zoo = App::getInstance('zoo');
	$link = $zoo->link(array('controller' => 'orders', 'task' => 'view', 'id' => $order->id));
?>

<div class="zoocart-container">
	<p><?php echo JText::_('PLG_ZOOCART_ORDER_SUCCESS_MESSAGE'); ?></p>
	<a class="btn btn-primary" href="<?php echo $link; ?>"><?php echo JText::_('PLG_ZOOCART_GO_TO_ORDER'); ?></a>
</div>