#Bixie - ZOOcart iDEAL payment

[![Join the chat at https://gitter.im/Bixie/Zoolander-ZOOcart-iDEAL](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/Bixie/Zoolander-ZOOcart-iDEAL?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Payment engine for ZOOlanders ZOOcart by www.zoolanders.com.

Thanks to www.ideal-checkout.nl, whose engine is integrated in this plugin.

Questions and bug-reports via [issues](https://github.com/Bixie/Zoolander-ZOOcart-iDEAL/issues) on GitHub.

_This plugin is not supported by or affiliated with ideal-checkout.nl. Support and questions only via this repository_

----

##Settings

Each gateway has its own settings. To make it uniform in the settings the fields key 1 through 3 are used. For more info on configuring the iDEAL settings, see www.ideal-checkout.nl.

###Rabo Omnikassa
_ideal-omnikassa_
* Key 1: Password used to generate hash
* Key 2: Hash key version
* Key 3: unused

###ING Advanced
_ideal-professional-v3_
* Key 1: Private key pass, password used to generate private key.
* Key 2: Name of your PRIVATE-KEY-FILE (should be located in `/plugins/zoocart_payment/ideal/idealcheckout/certificates/`!)
* Key 3: Name of your PRIVATE-CERTIFICATE-FILE (should be located in `/plugins/zoocart_payment/ideal/idealcheckout/certificates`!)

Don't use the auto-submit option, because the user has to select his bank first.

###ABN iDEAL Easy
_ideal-easy_

Only merchantID is required. Keys unused.

**This is not a valid or secure payment method! The result of the payment in the website is only a indication!**
**Confirmation of the payment can only come from the bank directly via dashboard or mail.**

###ABN Internetkassa
_ideal-internetkassa_
* Key 1: SHA1 IN key
* Key 2: SHA1 OUT key
* Key 3: unused

For more info on config on bank-side, see the [readme](https://github.com/Bixie/Zoolander-ZOOcart-iDEAL/tree/master/ideal/idealcheckout/gateways/ideal-internetkassa) in gateway folder (Dutch).
This method has not been properly tested yet. If you test it and it works, please let me know. If it doesn't work, please create an [issue](https://github.com/Bixie/Zoolander-ZOOcart-iDEAL/issues).


----
