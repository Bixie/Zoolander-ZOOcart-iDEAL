#Bixie - ZOOcart iDEAL payment

Payment engine for Zoolanders ZOOcart by www.zoolanders.com.

Thanks to http://www.ideal-checkout.nl, which engine is integrated in this plugin.

===
At the moment only Rabobank Omnikassa is active. iDEAL easy en iDEAL-internetkassa from ABN-AMRO are semi-prepared. Other gateways have to be prepared for this plugin.
Preparing a gateway is just a matter of copying the adjustments made to the Omnikassa.

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