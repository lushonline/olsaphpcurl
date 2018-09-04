<?PHP
/*****************************************************/
/* Class:   CURLWSSUserNameTokenSoapClient           */
/* Author:	Martin Holden, SkillSoft                 */
/* Date:	Aug 2018                                 */
/*                                                   */
/* Extends the WSSUserNameTokenSoapClient to use CURL*/
/*****************************************************/

class CURLWSSUserNameTokenSoapClient extends WSSUserNameTokenSoapClient{
	private $proxy;
	
	public function __getLastRequestHeaders() {
		return implode("\n", $this->__last_request_headers)."\n";
	}

	public function __construct($endpoint,$options) {
		if (array_key_exists("context",$options))
		{
			$contexts = stream_context_get_options($options["context"]);
			$context = $contexts['CURLWSSUserNameTokenSoapClient'];
			if (array_key_exists("proxy",$context))
			{
					$this->proxy=$context["proxy"];
			}
		}
	
		$result = parent::__construct($endpoint, $options);
		return $result;
	}
	
	function __doRequest($request, $location, $action, $version) {

    $headers = array(
      'Method: POST',
      'Connection: Keep-Alive',
      'User-Agent: PHP-SOAP-CURL',
      'Content-Type: text/xml; charset=utf-8',
      'SOAPAction: "'.$action.'"',
    );

    $this->__last_request_headers = $headers;
    $ch = curl_init($location);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	
	//Force TLS 1.2
	curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
	
	if (isset($this->proxy)) {
		curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, false);

		if (empty($this->proxy->proxyport)) {
			curl_setopt($ch, CURLOPT_PROXY, $this->proxy->proxyhost);
		} else {
			curl_setopt($ch, CURLOPT_PROXY, $this->proxy->proxyhost.':'.$this->proxy->proxyport);
		}

		if (!empty($this->proxy->proxyuser) and !empty($this->proxy->proxypassword)) {
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy->proxyuser.':'.$this->proxy->proxypassword);
			if (defined('CURLOPT_PROXYAUTH')) {
				// any proxy authentication if PHP 5.1
				curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC | CURLAUTH_NTLM);
			}
		}
	}
	
	
    $response = curl_exec($ch);
	
	if($errno = curl_errno($ch)) {
			$error_message = curl_strerror($errno);
			throw new Exception("[CURLWSSUserNameTokenSoapClient::__doRequest] ({$errno}):\n {$error_message}");
		}			

    return $response;
  }

	
}
?>