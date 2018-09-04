# About
Example code for performing [WS-Security UserNameToken](https://www.oasis-open.org/committees/download.php/13392/wss-v1.1-spec-pr-UsernameTokenProfile-01.htm) authentication for OLSA Web Services, utilising OpenSSL, cURL and PHP SoapClient

Minimum PHP version that supports TLS 1.2 is PHP 5.5.19, cURL 7.34.0 with OpenSSL 1.0.1

# Config
In the config.php you need to enter the OLSA endpoint url, customerid and shared secret

In PHP.INI you need to ensure the SOAP client and CURL extensions are enabled.

If you wish to use a Proxy, you can configure this in the [test.php](test.php) file.

# SOAP Client Code Details
The [CURLWSSUserNameTokenSoapClient.class.php](CURLWSSUserNameTokenSoapClient.class.php) contains an inherited implementation of the PHP client from [https://github.com/martinholden-skillsoft/olsaphpnocurl](https://github.com/martinholden-skillsoft/olsaphpnocurl).

The [CURLStream.class.php](CURLStream.class.php) is a stream wrapper that uses cURL for all communications, this stream wrapper needs to be registered to handle HTTPS before the CURLWSSUserNameTokenSoapClient is used. This is because by default the PHP SOAPClient uses the registered stream wrapper to read the WSDL.

# Testing
Run the [test.php](test.php) on the command line, it will attempt to call the SO_GetMultiActionSignOnExtended function with username [olsatest](test.php) and display the returned URL to use to seamlessly log the user in.

