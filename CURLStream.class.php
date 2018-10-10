<?PHP
/*****************************************************/
/* Class:   CURLStream                               */
/* Author:	Martin Holden, SkillSoft                 */
/* Date:	Oct 2018                                 */
/*                                                   */
/* Creates a new Stream Class use CURL as the default*/
/* so that we can have SOAPClient download WSDL using*/
/* CURL                                              */
/*****************************************************/

/* Inspired by https://thomas.rabaix.net/blog/2008/03/using-soap-php-with-ntlm-authentication */
/*
 * Copyright (c) 2008 Invest-In-France Agency http://www.invest-in-france.org
 *
 * Author : Thomas Rabaix
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

class CURLStream {
  private $path;
  private $mode;
  private $options;
  private $opened_path;
  private $buffer;
  private $pos;
  
  public $headers;
  public $info;
  public $context;

  const WRAPPER_NAME = "CURLStream";

  /**
   * Open the stream
   *
   * @param unknown_type $path
   * @param unknown_type $mode
   * @param unknown_type $options
   * @param unknown_type $opened_path
   * @return unknown
   */
  public function stream_open($path, $mode, $options, $opened_path) {
    //echo "[".self::WRAPPER_NAME."::stream_open] $path , mode=$mode \n";
    $this->path = $path;
    $this->mode = $mode;
    $this->options = $options;
    $this->opened_path = $opened_path;

    $this->createBuffer($path);

    return true;
  }

  /**
   * Close the stream
   *
   */
  public function stream_close() {
    //echo "[".self::WRAPPER_NAME."::stream_close] \n";
    curl_close($this->ch);
  }

  /**
   * Read the stream
   *
   * @param int $count number of bytes to read
   * @return content from pos to count
   */
  public function stream_read($count) {
    //echo "[".self::WRAPPER_NAME."::stream_read] $count \n";
    if(strlen($this->buffer) == 0) {
      return false;
    }

    $read = substr($this->buffer,$this->pos, $count);

    $this->pos += $count;

    return $read;
  }
  /**
   * write the stream
   *
   * @param int $count number of bytes to read
   * @return content from pos to count
   */
  public function stream_write($data) {
    //echo "[".self::WRAPPER_NAME."::stream_write] \n";
    if(strlen($this->buffer) == 0) {
      return false;
    }
    return true;
  }


  /**
   *
   * @return true if eof else false
   */
  public function stream_eof() {
    //echo "[".self::WRAPPER_NAME."::stream_eof] ";

    if($this->pos > strlen($this->buffer)) {
      //echo "true \n";
      return true;
    }

    //echo "false \n";
    return false;
  }

  /**
   * @return int the position of the current read pointer
   */
  public function stream_tell() {
    //echo "[".self::WRAPPER_NAME."::stream_tell] \n";
    return $this->pos;
  }

  /**
   * Flush stream data
   */
  public function stream_flush() {
    //echo "[".self::WRAPPER_NAME."::stream_flush] \n";
    $this->buffer = null;
    $this->pos = null;
  }

  /**
   * Stat the file, return only the size of the buffer
   *
   * @return array stat information
   */
  public function stream_stat() {
    //echo "[".self::WRAPPER_NAME."::stream_stat] \n";

    $this->createBuffer($this->path);
    $stat = array(
      'size' => strlen($this->buffer),
    );

    return $stat;
  }
  /**
   * Stat the url, return only the size of the buffer
   *
   * @return array stat information
   */
  public function url_stat($path, $flags) {
    //echo "[".self::WRAPPER_NAME."::url_stat] \n";
    $this->createBuffer($path);
    $stat = array(
      'size' => strlen($this->buffer),
    );

    return $stat;
  }

  /**
   * Create the buffer by requesting the url through cURL
   *
   * @param unknown_type $path
   */
  protected function createBuffer($path) {
    if($this->buffer) {
      return;
    }

	$contexts = stream_context_get_options($this->context);
	$context = $contexts[self::WRAPPER_NAME];

    $proxy = empty($context['proxy']) ? '' : $context['proxy'];
	
    //echo "[".self::WRAPPER_NAME."::createBuffer] create buffer from : $path\n";
    $this->ch = curl_init($path);
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
	
	//Turn of Certificate Verification as CURL does not have DigiCert CA
	curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
	
	//Force TLS 1.2
	curl_setopt($this->ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
	
	if (isset($proxy)) {
		curl_setopt($this->ch, CURLOPT_HTTPPROXYTUNNEL, false);

		if (empty($proxy->proxyport)) {
			curl_setopt($this->ch, CURLOPT_PROXY, $proxy->proxyhost);
		} else {
			curl_setopt($this->ch, CURLOPT_PROXY, $proxy->proxyhost.':'.$proxy->proxyport);
		}

		if (!empty($proxy->proxyuser) and !empty($proxy->proxypassword)) {
			curl_setopt($this->ch, CURLOPT_PROXYUSERPWD, $proxy->proxyuser.':'.$proxy->proxypassword);
			if (defined('CURLOPT_PROXYAUTH')) {
				// any proxy authentication if PHP 5.1
				curl_setopt($this->ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC | CURLAUTH_NTLM);
			}
		}
	}
	
	$headers = array();
	
	curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$headers) {
		$value = str_replace(array("\r", "\n"), '', $header);
		if (!empty($value)) {
			$headers[] = $value;
		}
		return strlen($header);
	});

	
	
    $this->buffer = curl_exec($this->ch);
	$this->info = curl_getinfo($this->ch);

	$this->headers = $headers;
	

	if($errno = curl_errno($this->ch)) {
			$error_message = curl_error($this->ch);
			throw new Exception("CURLStream ({$errno}):\n {$error_message}");
		}			
	
    //echo "[".self::WRAPPER_NAME."::createBuffer] buffer size : ".strlen($this->buffer)."bytes\n";
    $this->pos = 0;

  }
}
