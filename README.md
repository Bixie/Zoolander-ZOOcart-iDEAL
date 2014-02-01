#Bixie - ZOOcart iDEAL payment

Payment engine for ZOOlanders ZOOcart by www.zoolanders.com.

Thanks to www.ideal-checkout.nl, whose engine is integrated in this plugin.

----
At the moment only the Omnikassa (eg. Rabo) and Ideal professional (eg. ING) are active. iDEAL easy en iDEAL-internetkassa from ABN-AMRO are semi-prepared. Other gateways have to be prepared for this plugin.
Preparing a gateway is just a matter of copying the adjustments made to the Omnikassa.

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
For more info on config on bank-side, see the [readme](https://github.com/Bixie/Zoolander-ZOOcart-iDEAL/tree/master/ideal/idealcheckout/gateways/ideal-professional-v3) in gateway folder (Dutch).

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

----
