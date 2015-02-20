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
?>
<p><?php echo JText::_('PLG_ZOOCART_PAYMENT_IDEAL_ORDER_PLACED'); ?></p>
<?php
echo $formHtml;

if($auto): ?>
<script type="text/javascript">
jQuery(document).ready(function($){
	$('#zoocart-ideal [type="submit"]').trigger('click');
})
</script>
<?php endif;