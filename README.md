#Bixie - ZOOcart iDEAL payment

[![Join the chat at https://gitter.im/Bixie/Zoolander-ZOOcart-iDEAL](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/Bixie/Zoolander-ZOOcart-iDEAL?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Payment engine for ZOOlanders ZOOcart by www.zoolanders.com.

Thanks to www.ideal-checkout.nl, whose engine is integrated in this plugin.

Questions and bug-reports via [issues](https://github.com/Bixie/Zoolander-ZOOcart-iDEAL/issues) on GitHub.

----

__This plugin is not supported by or affiliated with ideal-checkout.nl. [Support and questions only via this repository](https://github.com/Bixie/Zoolander-ZOOcart-iDEAL/issues)__

----

##Settings

Each gateway has its own settings. To make it uniform in the settings the fields key 1 through 3 are used. For more info on configuring the iDEAL settings, see www.ideal-checkout.nl.
Listed below are the converted gateways. The gateways in the folder `/ideal/idealcheckout/gateways/disabled` have not yet been converted. Check the git-history on how to convert a gateway. I you have, please create a PR to merge it back in to the plugin.

###Rabo Omnikassa
_`/ideal/idealcheckout/gateways/ideal-omnikassa`_

* Id 1: PSPID (Obtained from bank)
* Id 2: SubID (usually 0)
* Key 1: Password used to generate hash
* Key 2: Hash key version
* Key 3: unused

###ING Advanced
_`/ideal/idealcheckout/gateways/ideal-professional-v3`_

* Id 1: PSPID (Obtained from bank)
* Id 2: SubID (usually 0)
* Key 1: Private key pass, password used to generate private key.
* Key 2: Name of your PRIVATE-KEY-FILE (should be located in `/plugins/zoocart_payment/ideal/idealcheckout/certificates/`!)
* Key 3: Name of your PRIVATE-CERTIFICATE-FILE (should be located in `/plugins/zoocart_payment/ideal/idealcheckout/certificates`!)

Don't use the auto-submit option, because the user has to select his bank first.

###ABN iDEAL Easy
_`/ideal/idealcheckout/gateways/ideal-easy`_

* Id 1: PSPID (Obtained from bank)
Only merchantID is required. Keys unused.

**This is not a valid or secure payment method! The result of the payment in the website is only a indication!**
**Confirmation of the payment can only come from the bank directly via dashboard or mail.**

###ABN Internetkassa
_`/ideal/idealcheckout/gateways/ideal-internetkassa`_

* Id 1: PSPID (Obtained from bank)
* Id 2: SubID (usually 0)
* Key 1: SHA1 IN key
* Key 2: SHA1 OUT key
* Key 3: unused

For more info on config on bank-side, see the [readme](https://github.com/Bixie/Zoolander-ZOOcart-iDEAL/tree/master/ideal/idealcheckout/gateways/ideal-internetkassa) in gateway folder (Dutch).
This method has not been properly tested yet. If you test it and it works, please let me know. If it doesn't work, please create an [issue](https://github.com/Bixie/Zoolander-ZOOcart-iDEAL/issues).

###Mollie
_`/ideal/idealcheckout/gateways/ideal-mollie`_
Using the Mollie REST api.

* Id 1: unused
* Id 2: unused
* Key 1: live API key
* Key 2: test API key
* Key 3: unused

Webhook url: `http://www.site.nl/index.php?option=com_zoolanders&controller=orders&task=callback&format=raw&payment_method=ideal&push=1`

Don't use the auto-submit option, because the user has to select his bank first.

###Sisow
_`/ideal/idealcheckout/gateways/ideal-sisow`_

* Id 1: Merchant ID
* Id 2: Shop ID (Obtained from Sisow, usually 0)
* Key 1: Merchant KEY
* Key 2: unused
* Key 3: unused

Don't use the auto-submit option, because the user has to select his bank first.

----

__This plugin is not supported by or affiliated with ideal-checkout.nl. [Support and questions only via this repository](https://github.com/Bixie/Zoolander-ZOOcart-iDEAL/issues)__

----
