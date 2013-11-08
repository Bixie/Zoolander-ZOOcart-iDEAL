<?php
/**
* @package		ZOOcart
* @author		ZOOlanders http://www.zoolanders.com
* @author		Matthijs Alles - Bixie
* @copyright	Copyright (C) JOOlanders SL
* @license		http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
*/

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');

class plgZoocart_paymentIdealInstallerScript
{
	protected $_error;
	protected $_src;
	protected $_target;
	protected $_ext = 'ideal';
	protected $_ext_name = 'iDEAL';
	protected $_lng_prefix = 'PLG_ZOOCART_PAYMENT_IDEAL';

	/**
	 * Called before any type of action
	 *
	 * @param   string  $type  Which action is happening (install|uninstall|discover_install)
	 * @param   object  $parent  The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function preflight($type, $parent)
	{
		// init vars
		$db = JFactory::getDBO();
		$type = strtolower($type);
		$this->_src = $parent->getParent()->getPath('source'); // tmp folder
		$this->_target = JPATH_ROOT.'/plugins/zoocart_payment/ideal'; // install folder

		// load ZLFW sys language file
		JFactory::getLanguage()->load('plg_system_zlframework.sys', JPATH_ADMINISTRATOR, 'en-GB', true);

		if($type == 'uninstall'){
			// save the sql files array while still exists
			$this->sqls = JFolder::files($this->_target . '/sql');
		}
	}

	/**
	 * Called on installation
	 *
	 * @param   object  $parent  The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	function install($parent)
	{
		// init vars
		$db = JFactory::getDBO();

		

		// enable plugin
		$db->setQuery("UPDATE `#__extensions` SET `enabled` = 1 WHERE `type` = 'plugin' AND `element` = '{$this->_ext}' AND `folder` = 'zoocart_payment'");
		$db->query();
	}

	/**
	 * Called on uninstallation
	 *
	 * @param   object  $parent  The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	function uninstall($parent)
	{
		// init vars
		$db = JFactory::getDBO();
		
		// disable all zoocart modules
		$db->setQuery("UPDATE `#__extensions` SET `enabled` = 0 WHERE `element` LIKE '%zoocart%'")->query();

		// drop tables
		if(is_array($this->sqls)) foreach($this->sqls as $sql)
		{
			$sql = basename($sql, '.sql');
			$db->setQuery('DROP TABLE IF EXISTS `#__zoo_zl_zoocart_' . $sql . '`')->query();
		}

		// enqueue Message
		JFactory::getApplication()->enqueueMessage(JText::_($this->langString('_UNINSTALL')));
	}

	/**
	 * Called after install
	 *
	 * @param   string  $type  Which action is happening (install|uninstall|discover_install)
	 * @param   object  $parent  The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function postflight($type, $parent)
	{
		// init vars
		$db = JFactory::getDBO();
		$type = strtolower($type);
		$release = $parent->get( "manifest" )->version;

		if($type == 'install'){
			echo JText::sprintf('PLG_ZLFRAMEWORK_SYS_INSTALL', $this->_ext_name, $release);
		}

		if($type == 'update'){
			echo JText::sprintf('PLG_ZLFRAMEWORK_SYS_UPDATE', $this->_ext_name, $release);
		}

		if($type != 'uninstall'){
		
			// create/update tables
			$sqls = JFolder::files($this->_src . '/sql');
			if(is_array($sqls)) foreach($sqls as $sql)
			{
				$sql = JFile::read($this->_src . '/sql/' . $sql);
				$queries = explode("-- QUERY SEPARATOR --", $sql);
				foreach($queries as $sql) {
					if ( !$db->setQuery($sql)->query() ) {
						$this->_error = 'ZL Error Query: ' . $sql . ' - ' . $db->getErrorMsg();
						return false;
					}
				}
			}
			
		}
	}

	/**
	 * creates the lang string
	 * @version 1.0
	 *
	 * @return  string
	 */
	protected function langString($string)
	{
		return $this->_lng_prefix.$string;
	}
}