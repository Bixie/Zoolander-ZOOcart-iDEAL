<?php

	if(file_exists(dirname(__FILE__) . '/debug.php'))
	{
		include_once(dirname(__FILE__) . '/debug.php');
	}

	if(file_exists(dirname(__FILE__) . '/update.order.status.php'))
	{
		include_once(dirname(__FILE__) . '/update.order.status.php');
	}


	// Create a random code with N digits.
	function idealcheckout_getRandomCode($iLength = 64)
	{
		$aCharacters = array('a', 'b', 'c', 'd', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');

		$sResult = '';

		for($i = 0; $i < $iLength; $i++)
		{
			$sResult .= $aCharacters[rand(0, sizeof($aCharacters) - 1)];
		}

		return $sResult;
	}


	// Find HASH salt
	function idealcheckout_getHashSalt($sStoreCode = false)
	{
		$aData = idealcheckout_getDatabaseSettings();
		return md5((is_string($sStoreCode) ? $sStoreCode : idealcheckout_getStoreCode()) . idealcheckout_serialize($aData));
	}


	// Find default store code
	function idealcheckout_getStoreCode()
	{
		return md5($_SERVER['SERVER_NAME']);
	}


	// Retrieve ROOT url of script
	function idealcheckout_getRootUrl($iParent = 0)
	{
		// Use a fixed ROOT_URL
		// return 'http://www.example.com/';
		$aWebsiteSettings = idealcheckout_getWebsiteSettings();

		if(!empty($aWebsiteSettings['root_url']))
		{
			if(substr($aWebsiteSettings['root_url'], -1, 1) == '/')
			{
				return $aWebsiteSettings['root_url'];
			}
			else
			{
				return $aWebsiteSettings['root_url'] . '/';
			}
		}

		// Detect installation directory based on current URL
		$sRootUrl = '';

		// Detect scheme
		if(isset($_SERVER['HTTPS']) && (strcasecmp($_SERVER['HTTPS'], 'ON') === 0))
		{
			$sRootUrl .= 'https://';
		}
		else
		{
			$sRootUrl .= 'http://';
		}

		// Detect domain
		$sRootUrl .= $_SERVER['HTTP_HOST'];

		// Detect port
		if((strpos($_SERVER['HTTP_HOST'], ':') === false) && isset($_SERVER['SERVER_PORT']) && (strcmp($_SERVER['SERVER_PORT'], '80') !== 0))
		{
			$sRootUrl .= ':' . $_SERVER['SERVER_PORT'];
		}

		$sRootUrl .= '/';

		// Detect path
		if(isset($_SERVER['SCRIPT_NAME']))
		{
			$a = explode('/', substr($_SERVER['SCRIPT_NAME'], 1));

			while(sizeof($a) > ($iParent + 1))
			{
				$sRootUrl .= $a[0] . '/';
				array_shift($a);
			}
		}

		return $sRootUrl;
	}


	// Retrieve ROOT url of script
	function idealcheckout_getRootPath()
	{
		return dirname(dirname(__FILE__)) . '/';
	}


	// Escape SQL values
	function idealcheckout_escapeSql($sString, $bEscapeLike = false)
	{
		if($bEscapeLike)
		{
			// _ : represents a single character in a LIKE value
			// % : represents 0 or more character in a LIKE value
			$sString = str_replace(array('\\', '\'', '_', '%'), array('\\\\', '\\\'', '\\_', '\\%'), $sString);
		}
		else
		{
			$sString = str_replace(array('\\', '\''), array('\\\\', '\\\''), $sString);
		}

		return $sString;
	}


	// Serialize data
	function idealcheckout_serialize($sString)
	{
		return serialize($sString);
	}


	// Unserialize data
	function idealcheckout_unserialize($sString)
	{
		// Recalculate multibyte strings
		$sString = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $sString);
		return unserialize($sString);
	}


	// Load data from an URL
	function idealcheckout_doHttpRequest($sUrl, $sPostData = false, $bRemoveHeaders = false, $iTimeout = 30, $bDebug = false)
	{
		if(in_array('sockets', get_loaded_extensions())) // Prefer FSOCK
		{
			return idealcheckout_doHttpRequest_fsock($sUrl, $sPostData, $bRemoveHeaders, $iTimeout, $bDebug);
		}
		elseif(in_array('curl', get_loaded_extensions()))
		{
			return idealcheckout_doHttpRequest_curl($sUrl, $sPostData, $bRemoveHeaders, $iTimeout, $bDebug);
		}
		else
		{
			idealcheckout_die('idealcheckout_doHttpRequest: Cannot detect sockets or curl.', __FILE__, __LINE__, false);
		}
	}


	// doHttpRequest (Uses sockets-library)
	function idealcheckout_doHttpRequest_fsock($sUrl, $sPostData = false, $bRemoveHeaders = false, $iTimeout = 30, $bDebug = false)
	{
		$aUrl = parse_url($sUrl);

		$sRequestUrl = '';

		if(in_array($aUrl['scheme'], array('ssl', 'https')))
		{
			$sRequestUrl .= 'ssl://';

			if(empty($aUrl['port']))
			{
				$aUrl['port'] = 443;
			}
		}
		elseif(empty($aUrl['port']))
		{
			$aUrl['port'] = 80;
		}

		$sRequestUrl .= $aUrl['host'];
		$iRequestPort = intval($aUrl['port']);

		$sErrorNumber = 0;
		$sErrorMessage = '';

		$oSocket = fsockopen($sRequestUrl, $iRequestPort, $sErrorNumber, $sErrorMessage, $iTimeout);
		$sResponse = '';

		if($oSocket)
		{
			$sRequest = ($sPostData ? 'POST' : 'GET') . ' ' . (empty($aUrl['path']) ? '/' : $aUrl['path']) . (empty($aUrl['query']) ? '' : '?' . $aUrl['query']) . ' HTTP/1.0' . "\r\n";
			$sRequest .= 'Host: ' . $aUrl['host'] . "\r\n";
			$sRequest .= 'Accept: text/html' . "\r\n";
			$sRequest .= 'Accept-Charset: charset=ISO-8859-1,utf-8' . "\r\n";

			if(is_array($sPostData))
			{
				$sPostData = str_replace(array('%5B', '%5D'), array('[', ']'), http_build_query($sPostData));
			}

			if($sPostData)
			{
				$sRequest .= 'Content-Length: ' . strlen($sPostData) . "\r\n";
				$sRequest .= 'Content-Type: application/x-www-form-urlencoded; charset=utf-8' . "\r\n" . "\r\n";
				$sRequest .= $sPostData;
			}
			else
			{
				$sRequest .= "\r\n";
			}


			if($bDebug === true)
			{
				echo "\r\n" . "\r\n" . '<h1>SEND DATA:</h1>' . "\r\n" . '<code style="display: block; background: #E0E0E0; border: #000000 solid 1px; padding: 10px;">' . str_replace(array("\n", "\r"), array('<br>' . "\r\n", ''), htmlspecialchars($sRequest)) . '</code>' . "\r\n" . "\r\n";
			}


			// Send data
			fputs($oSocket, $sRequest);

			// Recieve data
			while(!feof($oSocket))
			{
				$sResponse .= @fgets($oSocket, 128);
			}

			fclose($oSocket);


			if($bDebug === true)
			{
				echo "\r\n" . "\r\n" . '<h1>RECIEVED DATA:</h1>' . "\r\n" . '<code style="display: block; background: #E0E0E0; border: #000000 solid 1px; padding: 10px;">' . str_replace(array("\n", "\r"), array('<br>' . "\r\n", ''), htmlspecialchars($sResponse)) . '</code>' . "\r\n" . "\r\n";
			}


			if($bRemoveHeaders) // Remove headers from reply?
			{
				list($sHeader, $sBody) = preg_split('/(\\r?\\n){2,2}/', $sResponse, 2);
				return $sBody;
			}
			else
			{
				return $sResponse;
			}
		}
		else
		{
			if($bDebug)
			{
				echo "\r\n" . "\r\n" . 'Cannot connect to: ' . htmlspecialchars($sRequestUrl);
			}

			die('Socket error: ' . htmlspecialchars($sErrorMessage));
		}
	}


	// doHttpRequest (Uses curl-library)
	function idealcheckout_doHttpRequest_curl($sUrl, $sPostData = false, $bRemoveHeaders = false, $iTimeout = 30, $bDebug = false)
	{
		$aUrl = parse_url($sUrl);

		$sRequestUrl = '';

		if(in_array($aUrl['scheme'], array('ssl', 'https')))
		{
			$sRequestUrl .= 'https://';

			if(empty($aUrl['port']))
			{
				$aUrl['port'] = 443;
			}
		}
		else
		{
			$sRequestUrl .= 'http://';

			if(empty($aUrl['port']))
			{
				$aUrl['port'] = 80;
			}
		}

		$sRequestUrl .= $aUrl['host'] . (empty($aUrl['path']) ? '/' : $aUrl['path']);

		if(is_array($sPostData))
		{
			$sPostData = str_replace(array('%5B', '%5D'), array('[', ']'), http_build_query($sPostData));
		}


		if($bDebug === true)
		{
			$sRequest  = 'Requested URL: ' . $sRequestUrl . "\r\n";
			$sRequest .= 'Portnumber: ' . $aUrl['port'] . "\r\n";

			if($sPostData)
			{
				$sRequest .= 'Posted data: ' . $sPostData . "\r\n";
			}

			echo "\r\n" . "\r\n" . '<h1>SEND DATA:</h1>' . "\r\n" . '<code style="display: block; background: #E0E0E0; border: #000000 solid 1px; padding: 10px;">' . str_replace(array("\n", "\r"), array('<br>' . "\r\n", ''), htmlspecialchars($sRequest)) . '</code>' . "\r\n" . "\r\n";
		}


		$oCurl = curl_init();

		curl_setopt($oCurl, CURLOPT_URL, $sRequestUrl);
		curl_setopt($oCurl, CURLOPT_PORT, $aUrl['port']);
		curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($oCurl, CURLOPT_TIMEOUT, $iTimeout);
		curl_setopt($oCurl, CURLOPT_HEADER, $bRemoveHeaders == false);

		if($sPostData != false)
		{
			curl_setopt($oCurl, CURLOPT_POST, true);
			curl_setopt($oCurl, CURLOPT_POSTFIELDS, $sPostData);
		}

		$sResponse = curl_exec($oCurl);
		curl_close($oCurl);


		if($bDebug === true)
		{
			echo "\r\n" . "\r\n" . '<h1>RECIEVED DATA:</h1>' . "\r\n" . '<code style="display: block; background: #E0E0E0; border: #000000 solid 1px; padding: 10px;">' . str_replace(array("\n", "\r"), array('<br>' . "\r\n", ''), htmlspecialchars($sResponse)) . '</code>' . "\r\n" . "\r\n";
		}


		if(empty($sResponse))
		{
			return '';
		}

		return $sResponse;	
	}


	// Print html to screen
	function idealcheckout_output($sHtml, $bImage = true)
	{
		global $aIdealCheckout;

		// Detect idealcheckout folder
		$sRootUrl = idealcheckout_getRootUrl();
		
		if(($iStrPos = strpos($sRootUrl, '/idealcheckout/')) !== false)
		{
			$sRootUrl = substr($sRootUrl, 0, $iStrPos) . '/';
		}

		// Detect gateway name & image
		$sTitle = 'Checkout';
		$sImage = 'gateway.jpg';
		$sColor = '#999999';

		if(!empty($aIdealCheckout['record']['gateway_code']))
		{
			if(strcasecmp($aIdealCheckout['record']['gateway_code'], 'ideal') === 0)
			{
				$sTitle = 'iDEAL Checkout';
				$sImage = 'ideal.gif';
				$sColor = '#CC0066';
			}
			elseif(strcasecmp($aIdealCheckout['record']['gateway_code'], 'minitix') === 0)
			{
				$sTitle = 'MiniTix Checkout';
				$sImage = 'minitix.png';
				$sColor = '#FFCC00';
			}
			elseif(strcasecmp($aIdealCheckout['record']['gateway_code'], 'mistercash') === 0)
			{
				$sTitle = 'MisterCash Checkout';
				$sImage = 'mistercash.gif';
				$sColor = '#0083C6';
			}
			elseif(strcasecmp($aIdealCheckout['record']['gateway_code'], 'directebanking') === 0)
			{
				$sTitle = 'Direct E-Banking Checkout';
				$sImage = 'directebanking.png';
				$sColor = '#F18E00';
			}
			elseif(strcasecmp($aIdealCheckout['record']['gateway_code'], 'creditcard') === 0)
			{
				$sTitle = 'CreditCard Checkout';
				$sImage = 'creditcard.jpg';
			}
			elseif(strcasecmp($aIdealCheckout['record']['gateway_code'], 'paysafecard') === 0)
			{
				$sTitle = 'PaySafeCard Checkout';
				$sImage = 'paysafecard.png';
				$sColor = '#008ACA';
			}
			elseif(strcasecmp($aIdealCheckout['record']['gateway_code'], 'paypal') === 0)
			{
				$sTitle = 'PayPal Checkout';
				$sImage = 'paypal.png';
			}
		}


		echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
	<head>
		<title>' . $sTitle . '</title>
		<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-15">
		<style type="text/css">

html, body, form, div
{
	margin: 0px;
	padding: 0px;
}

div.wrapper
{
	padding: 50px 0px 0px 0px;
	text-align: center;
}

p
{
	font-family: Arial;
	font-size: 15px;
}

a
{
	color: ' . $sColor . ' !important;
}

td
{
	font-family: Arial;
	font-size: 12px;
}

		</style>

	</head>
	<body>

		<!-- 

			This ' . $sTitle . ' script is developed by:

			iDEAL Checkout

			Support & Information:
			E. info@ideal-checkout.nl
			W. http://www.ideal-checkout.nl
			T. +31614707337

		-->

		<div class="wrapper">
			' . ($bImage ? '<p><img alt="' . $sTitle . '" border="0" src="' . $sRootUrl . 'idealcheckout/images/' . $sImage . '"></p>' : '') . '

' . $sHtml . '

		</div>

	</body>
</html>';

		exit;
	}


	// Translate text using language files
	function idealcheckout_getTranslation($sLanguageCode = false, $sGroup, $sKey, $aParams = array())
	{
		global $aIdealCheckout;

		if(empty($sLanguageCode))
		{
			if(!empty($aIdealCheckout['record']['language']))
			{
				$sLanguageCode = strtolower($aIdealCheckout['record']['language']);
			}
			elseif(!empty($aIdealCheckout['language']))
			{
				$sLanguageCode = strtolower($aIdealCheckout['language']);
			}
			else
			{
				$sLanguageCode = 'en';
			}
		}

		if(!isset($aIdealCheckout['translations'][$sLanguageCode][$sGroup]))
		{
			$sTranslationFile = dirname(dirname(__FILE__)) . '/translations/' . $sGroup . '.' . $sLanguageCode . '.php';

			if(file_exists($sTranslationFile))
			{
				$aIdealCheckout['translations'][$sLanguageCode][$sGroup] = include_once($sTranslationFile);
			}
		}

		if(isset($aIdealCheckout['translations'][$sLanguageCode][$sGroup][$sKey]))
		{
			$sText = $aIdealCheckout['translations'][$sLanguageCode][$sGroup][$sKey];
		}
		else
		{
			$sText = $sKey;
		}

		if(is_array($aParams) && sizeof($aParams))
		{
			foreach($aParams as $k => $v)
			{
				$sText = str_replace('{' . $k . '}', $v, $sText);
			}
		}

		return $sText;
	}


	// Load database settings
	function idealcheckout_getDatabaseSettings($sStoreCode = false)
	{
		global $aIdealCheckout;

		if(empty($sStoreCode))
		{
			if(!empty($aIdealCheckout['record']['store_code']))
			{
				$sStoreCode = $aIdealCheckout['record']['store_code'];
			}
			else
			{
				$sStoreCode = idealcheckout_getStoreCode();
			}
		}

		$sDatabaseFile1 = dirname(dirname(__FILE__)) . '/configuration/database.' . strtolower($sStoreCode) . '.php';
		$sDatabaseFile2 = dirname(dirname(__FILE__)) . '/configuration/database.php';
		$sDatabaseError = 'No configuration file available for database.';

		$aSettings = array();

		// Database Server/Host
		$aSettings['host'] = 'localhost';

		// Database Type
		$aSettings['type'] = 'mysql';

		// Database Username
		$aSettings['user'] = '';

		// Database Password
		$aSettings['pass'] = '';

		// Database Name
		$aSettings['name'] = '';

		// Database Table Prefix (if any)
		$aSettings['prefix'] = '';

		// iDEAL Checkout Table
		$aSettings['table'] = '';

		if(file_exists($sDatabaseFile1) && @is_file($sDatabaseFile1) && @is_readable($sDatabaseFile1))
		{
			include($sDatabaseFile1);
		}
		elseif(file_exists($sDatabaseFile2) && @is_file($sDatabaseFile2) && @is_readable($sDatabaseFile2))
		{
			include($sDatabaseFile2);
		}
		else
		{
			idealcheckout_die('ERROR: ' . $sDatabaseError, __FILE__, __LINE__, false);
		}


		
		// iDEAL Checkout Table
		if(empty($aSettings['table']))
		{
			$aSettings['table'] = $aSettings['prefix'] . 'zoo_zl_zoocart_idealcheckout'; //bixie
		}

		return $aSettings;
	}


	// Load database settings
	function idealcheckout_getWebsiteSettings($sStoreCode = false)
	{
		global $aIdealCheckout;

		if(empty($sStoreCode))
		{
			if(!empty($aIdealCheckout['record']['store_code']))
			{
				$sStoreCode = $aIdealCheckout['record']['store_code'];
			}
			else
			{
				$sStoreCode = idealcheckout_getStoreCode();
			}
		}

		$sWebsiteFile1 = dirname(dirname(__FILE__)) . '/configuration/website.' . strtolower($sStoreCode) . '.php';
		$sWebsiteFile2 = dirname(dirname(__FILE__)) . '/configuration/website.php';
		$sWebsiteError = 'No configuration file available for website.';

		$aSettings = array();

		if(file_exists($sWebsiteFile1) && @is_file($sWebsiteFile1) && @is_readable($sWebsiteFile1))
		{
			include($sWebsiteFile1);
		}
		elseif(file_exists($sWebsiteFile2) && @is_file($sWebsiteFile2) && @is_readable($sWebsiteFile2))
		{
			include($sWebsiteFile2);
		}
		else
		{
			// idealcheckout_die('ERROR: ' . $sWebsiteError, __FILE__, __LINE__, false);
		}

		return $aSettings;
	}


	// Load gateway settings
	//bixie
	function idealcheckout_getGatewaySettings($sStoreCode = false, $sGatewayCode = false, $settingsData=array())
	{
		global $aIdealCheckout;

		if(empty($sStoreCode))
		{
			if(!empty($aIdealCheckout['record']['store_code']))
			{
				$sStoreCode = $aIdealCheckout['record']['store_code'];
			}
			else
			{
				$sStoreCode = idealcheckout_getStoreCode();
			}
		}

		if(empty($sGatewayCode))
		{
			if(!empty($aIdealCheckout['record']['gateway_code']))
			{
				$sGatewayCode = $aIdealCheckout['record']['gateway_code'];
			}
			else
			{
				$sGatewayCode = 'ideal';
			}
		}



		if(!preg_match('/^([a-zA-Z0-9_\-]+)$/', $sGatewayCode))
		{
			idealcheckout_die('INVALID GATEWAY: ' . $sGatewayCode, __FILE__, __LINE__, false);
		}
		elseif(!preg_match('/^([a-zA-Z0-9_\-]+)$/', $sStoreCode))
		{
			idealcheckout_die('INVALID STORE CODE: ' . $sStoreCode, __FILE__, __LINE__, false);
		}


		$sConfigurationPath = dirname(dirname(__FILE__)) . '/configuration/';
		$sConfigFile1 = $sConfigurationPath . strtolower($sGatewayCode) . '.' . strtolower($sStoreCode) . '.php';
		$sConfigFile2 = $sConfigurationPath . strtolower($sGatewayCode) . '.php';
		$sConfigError = 'No configuration file available for ' . $sGatewayCode . '.';



		$aSettings = array();
		//bixie
		if (count($settingsData)) {
			$aSettings = $settingsData;
		} else {
			if(file_exists($sConfigFile1) && @is_file($sConfigFile1) && @is_readable($sConfigFile1))
			{
				include($sConfigFile1);
			}
			elseif(file_exists($sConfigFile2) && @is_file($sConfigFile2) && @is_readable($sConfigFile2))
			{
				include($sConfigFile2);
			}
			else
			{
				idealcheckout_die('ERROR: ' . $sConfigError, __FILE__, __LINE__, false);
			}
		}



		if(empty($aSettings['TEST_MODE']))
		{
			$aSettings['TEST_MODE'] = false;
		}


		// Fix temp path
		if(empty($aSettings['TEMP_PATH']))
		{
			$aSettings['TEMP_PATH'] = dirname(dirname(__FILE__)) . '/temp/';
		}


		// Fix certificate path
		if(empty($aSettings['CERTIFICATE_PATH']))
		{
			$aSettings['CERTIFICATE_PATH'] = dirname(dirname(__FILE__)) . '/certificates/';			
		}


		// Fix gateway path
		if(!empty($aSettings['GATEWAY_METHOD']))
		{
			$aSettings['GATEWAY_FILE'] = dirname(dirname(__FILE__)) . '/gateways/' . $aSettings['GATEWAY_METHOD'] . '/gateway.cls.php';
		}
		elseif(strcasecmp(substr($aSettings['GATEWAY_FILE'], 0, 10), '/gateways/') === 0)
		{
			$aSettings['GATEWAY_FILE'] = dirname(dirname(__FILE__)) . $aSettings['GATEWAY_FILE'];
		}
		elseif(strcasecmp(substr($aSettings['GATEWAY_FILE'], 0, 9), 'gateways/') === 0)
		{
			$aSettings['GATEWAY_FILE'] = dirname(dirname(__FILE__)) . '/' . $aSettings['GATEWAY_FILE'];
		}

		return $aSettings;
	}

	function idealcheckout_die($sError, $sFile = false, $iLine = false, $sGatewayCode = 'ideal')
	{
		$bDebugMode = false;

		if(file_exists(dirname(__FILE__) . '/debug.php'))
		{
			$bDebugMode = true;
		}
		elseif($sGatewayCode)
		{
			$aGatewaySettings = idealcheckout_getGatewaySettings(false, $sGatewayCode);

			if(!empty($aGatewaySettings['test_mode']))
			{
				$bDebugMode = true;
			}
		}

		if($bDebugMode)
		{
			$sError = str_replace(array("\r\n", "\r", "\n"), '<br>', htmlentities($sError));

			echo $sError;

			if($sFile !== false)
			{
				echo '<br><br>FILE: ' . $sFile;
			}

			if($iLine !== false)
			{
				echo '<br><br>LINE: ' . $iLine;
			}
		}
		else
		{
			echo 'A fatal error has occured.';
			idealcheckout_log($sError, $sFile, $iLine);
		}

		exit;
	}

	function idealcheckout_log($sText, $sFile = false, $iLine = false)
	{
		if(is_array($sText) || is_object($sText))
		{
			$sText = var_export($sText, true);
		}

		// Reformat text
		$sText = str_replace("\n", "\n      ", trim($sText));

		$sLog = "\n" . 'TEXT: ' . $sText . "\n";
		
		if($sFile !== false)
		{
			$sLog .= 'FILE: ' . $sFile . "\n";
		}

		if($sFile !== false)
		{
			$sLog .= 'LINE: ' . $iLine . "\n";
		}

		$sLog .= "\n";


		$sLogFile = dirname(dirname(__FILE__)) . '/temp/' . date('Ymd.His') . '.log';

		file_put_contents($sLogFile, $sLog, FILE_APPEND);
		chmod($sLogFile, 0777);
	}

	function idealcheckout_database_setup($oDatabaseConnection = false)
	{
		global $aIdealCheckout;

		if(empty($aIdealCheckout['database']['connection']))
		{
			// Find database configuration
			$aIdealCheckout['database'] = idealcheckout_getDatabaseSettings();

			// Connect to database
			$aIdealCheckout['database']['connection'] = idealcheckout_database_connect($aIdealCheckout['database']['host'], $aIdealCheckout['database']['user'], $aIdealCheckout['database']['pass']) or idealcheckout_die('ERROR: Cannot connect to ' . $aIdealCheckout['database']['type'] . ' server. Error in hostname, username and/or password.', __FILE__, __LINE__, false);
			idealcheckout_database_select_db($aIdealCheckout['database']['connection'], $aIdealCheckout['database']['name']) or idealcheckout_die('ERROR: Cannot find database `' . $aIdealCheckout['database']['name'] . '` on ' . $aIdealCheckout['database']['host'] . '.', __FILE__, __LINE__, false);
		}

		return $aIdealCheckout['database']['connection'];
	}


	function idealcheckout_database_query($sQuery, $oDatabaseConnection = false)
	{
		global $aIdealCheckout;

		if($oDatabaseConnection === false)
		{
			$oDatabaseConnection = idealcheckout_database_setup();
		}
		
		if(!empty($aIdealCheckout['database']['type']) && (strcmp($aIdealCheckout['database']['type'], 'mysqli') === 0))
		{
			return mysqli_query($oDatabaseConnection, $sQuery);
		}
		else
		{
			return mysql_query($sQuery, $oDatabaseConnection);
		}
	}


	function idealcheckout_database_getRecord($sQuery, $oDatabaseConnection = false)
	{
		$aRecords = idealcheckout_database_getRecords($sQuery, $oDatabaseConnection);

		if(sizeof($aRecords) > 0)
		{
			return $aRecords[0];
		}

		return false;
	}


	function idealcheckout_database_getRecords($sQuery, $oDatabaseConnection = false)
	{
		global $aIdealCheckout;

		if($oDatabaseConnection === false)
		{
			$oDatabaseConnection = idealcheckout_database_setup();
		}

		$aRecords = array();
		
		if(!empty($aIdealCheckout['database']['type']) && (strcmp($aIdealCheckout['database']['type'], 'mysqli') === 0))
		{
			if($oRecordset = mysqli_query($oDatabaseConnection, $sQuery))
			{
				while($aRecord = mysqli_fetch_assoc($oRecordset))
				{
					$aRecords[] = $aRecord;
				}

				mysqli_free_result($oRecordset);
			}
		}
		else
		{
			if($oRecordset = mysql_query($sQuery, $oDatabaseConnection))
			{
				while($aRecord = mysql_fetch_assoc($oRecordset))
				{
					$aRecords[] = $aRecord;
				}

				mysql_free_result($oRecordset);
			}
		}

		return $aRecords;
	}


	function idealcheckout_database_error($oDatabaseConnection = false)
	{
		global $aIdealCheckout;

		if($oDatabaseConnection === false)
		{
			$oDatabaseConnection = idealcheckout_database_setup();
		}
		
		if(!empty($aIdealCheckout['database']['type']) && (strcmp($aIdealCheckout['database']['type'], 'mysqli') === 0))
		{
			return @mysqli_error($oDatabaseConnection);
		}
		else
		{
			return @mysql_error($oDatabaseConnection);
		}
	}
	

	function idealcheckout_database_fetch_assoc($oRecordSet)
	{
		global $aIdealCheckout;
		
		if(!empty($aIdealCheckout['database']['type']) && (strcmp($aIdealCheckout['database']['type'], 'mysqli') === 0))
		{
			return mysqli_fetch_assoc($oRecordSet);
		}
		else
		{
			return mysql_fetch_assoc($oRecordSet);
		}
	}
	

	function idealcheckout_database_connect($oDatabaseConnection = false)
	{
		global $aIdealCheckout;
		
		if(!empty($aIdealCheckout['database']['type']) && (strcmp($aIdealCheckout['database']['type'], 'mysqli') === 0))
		{
			return mysqli_connect($aIdealCheckout['database']['host'], $aIdealCheckout['database']['user'], $aIdealCheckout['database']['pass']);
		}
		else
		{
			return mysql_connect($aIdealCheckout['database']['host'], $aIdealCheckout['database']['user'], $aIdealCheckout['database']['pass']);
		}
	}

	
	function idealcheckout_database_select_db($oDatabaseConnection = false, $sDatabaseName = false)
	{
		global $aIdealCheckout;

		if($oDatabaseConnection === false)
		{
			$oDatabaseConnection = idealcheckout_database_setup();
		}
		
		if(!empty($aIdealCheckout['database']['type']) && (strcmp($aIdealCheckout['database']['type'], 'mysqli') === 0))
		{
			return mysqli_select_db($oDatabaseConnection, $sDatabaseName);
		}
		else
		{
			return mysql_select_db($sDatabaseName, $oDatabaseConnection);
		}
	}

	
	function idealcheckout_database_num_rows($oRecordSet)
	{
		global $aIdealCheckout;
		
		if(!empty($aIdealCheckout['database']['type']) && (strcmp($aIdealCheckout['database']['type'], 'mysqli') === 0))
		{
			return mysqli_num_rows($oRecordSet);
		}
		else
		{
			return mysql_num_rows($oRecordSet);
		}
	}

	
	function idealcheckout_database_insert_id($oDatabaseConnection = false)
	{
		global $aIdealCheckout;

		if($oDatabaseConnection === false)
		{
			$oDatabaseConnection = idealcheckout_database_setup();
		}
		
		if(!empty($aIdealCheckout['database']['type']) && (strcmp($aIdealCheckout['database']['type'], 'mysqli') === 0))
		{
			return mysqli_insert_id($oDatabaseConnection);
		}
		else
		{
			return mysql_insert_id($oDatabaseConnection);
		}
	}

	function idealcheckout_getPaymentButton($aParams, $sSubmitButton = 'Afrekenen', $sFormUrl = 'idealcheckout/checkout.php')
	{
		$sHtml = '<form action="' . htmlspecialchars($sFormUrl) . '" method="post">';

		foreach($aParams as $k => $v)
		{
			$sHtml .= '<input name="' . htmlspecialchars($k) . '" type="hidden" value="' . htmlspecialchars($v) . '">';
		}

		if(strpos($sSubmitButton, '://') !== false)
		{
			$sHtml .= '<input type="image" src="' . htmlspecialchars($sSubmitButton) . '">';
		}
		elseif(strpos($sSubmitButton, '<input') !== false)
		{
			$sHtml .= $sSubmitButton;
		}
		else
		{
			$sHtml .= '<input type="submit" value="' . htmlspecialchars($sSubmitButton) . '">';		
		}

		$sHtml .= '</form>';


		return $sHtml;
	}

	function idealcheckout_php_execute($_____CODE, $_____PARAMS = array())
	{
		foreach($_____PARAMS as $k => $v)
		{
			${$k} = $v;
		}

		$_____CODE = trim($_____CODE);

		if(strcasecmp(substr($_____CODE, 0, 5), '<' . '?' . 'php') === 0)
		{
			$_____CODE = substr($_____CODE, 5);
		}
		elseif(strcasecmp(substr($_____CODE, 0, 2), '<' . '?') === 0)
		{
			$_____CODE = substr($_____CODE, 2);
		}

		if(strcasecmp(substr($_____CODE, -2, 2), '?' . '>') === 0)
		{
			$_____CODE = substr($_____CODE, 0, -2);
		}

		$_____CODE = trim($_____CODE);

		eval($_____CODE);
	}

	function idealcheckout_mailStatusUpdate($sFromName, $sFromEmail, $sToEmail, $oRecord, $sOrderUrl = false, $sBackendUrl = false)
	{
		$sMailTo = $sToEmail;
		$sMailSubject = 'iDEAL Checkout Transaction Update: ' . $oRecord['transaction_description'];
		$sMailMessage = '';
		$sMailHeaders = 'From: "' . $sFromName . '" <' . $sFromEmail . '>';

		$sOrderParams = '';

		if(empty($oRecord['order_params']))
		{
			$sOrderParams .= '';
		}
		elseif(substr($oRecord['order_params'], 0, 2) == 'a:') // serialized
		{
			$aOrderParams = idealcheckout_unserialize($oRecord['order_params']);
			$sOrderParams .= idealcheckout_arrayToText($aOrderParams, 15);
		}

		// Send email to webmaster
		$sMailMessage .= '
iDEAL Checkout Transaction Update

Order:         ' . $oRecord['order_id'] . '
Bedrag:        ' . $oRecord['transaction_amount'] . '
Omschrijving:  ' . $oRecord['transaction_description'] . '

Params:        ' . trim($sOrderParams ? $sOrderParams : '-') . '

Transactie:    ' . $oRecord['transaction_id'] . '
Status:        ' . $oRecord['transaction_status'] . '


Controleer de definitieve status van transacties ALTIJD via uw iDEAL Dashboard of bankafschrift.
';

		if($sOrderUrl)
		{
			$sMailMessage .= '

U kunt deze order bekijken/beheren via onderstaande url:
' . $sOrderUrl;
		}
		elseif($sBackendUrl)
		{
			$sMailMessage .= '

U kunt deze order bekijken/beheren via de beheeromgeving van uw webshop:
' . $sBackendUrl;
		}

		@mail($sMailTo, $sMailSubject, $sMailMessage, $sMailHeaders);
	}

	function idealcheckout_arrayToText($aArray, $iWhiteSpace = 0)
	{
		$sData = '';

		if(is_array($aArray) && sizeof($aArray))
		{
			foreach($aArray as $k1 => $v1)
			{
				if(strlen($sData))
				{
					$sData .= "\n";
				}

				$sData .= str_repeat(' ', $iWhiteSpace) . $k1 . ': ';

				if(is_object($v1))
				{
					$sData .= '[' . get_class($v1) . ' object], ';
				}
				elseif(is_array($v1))
				{
					$sData .= "\n" . idealcheckout_arrayToText($v1, $iWhiteSpace + strlen($k1) + 2) . ', ';
				}
				elseif($v1 === true)
				{
					$sData .= 'TRUE, ';
				}
				elseif($v1 === false)
				{
					$sData .= 'FALSE, ';
				}
				elseif($v1 === null)
				{
					$sData .= 'NULL, ';
				}
				else
				{
					$sData .= $v1 . ', ';
				}
			}

			$sData = substr($sData, 0, -2); // Remove last comma-space
		}

		return $sData;
	}

?>