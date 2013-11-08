<?php
//bixie
if (file_exists(JPATH_ROOT . '/configuration.php')) {
	include_once JPATH_ROOT . '/configuration.php';
	
	$jConfig = new JConfig();
	
	// MySQL Server/Host
	$aSettings['host'] = $jConfig->host;
	
	// MySQL Username
	$aSettings['user'] = $jConfig->user;

	// MySQL Password
	$aSettings['pass'] = $jConfig->password;

	// MySQL Database name
	$aSettings['name'] = $jConfig->db;

	// MySQL Table Prefix
	$aSettings['prefix'] = $jConfig->dbprefix;

	// MySQL Engine (MySQL or MySQLi)
	$aSettings['type'] = 'mysqli';
}




?>