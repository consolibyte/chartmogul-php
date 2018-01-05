<?php

namespace ChartMogul\Util;

class Http
{
	const HTTP_400 = 400;
	const HTTP_401 = 401;
	const HTTP_500 = 500;

	protected $_url;

	protected $_request_headers;

	protected $_body;

	protected $_post;

	protected $_get;

	protected $_last_response;

	protected $_last_request;

	protected $_last_duration;

	protected $_last_info;

	protected $_errnum;

	protected $_errmsg;

	protected $_verify_peer;

	protected $_verify_host;

	protected $_certificate;

	protected $_log;

	protected $_debug;

	protected $_return_headers;

	protected $_username;
	protected $_password;

	/**
	 * Create a new object to make HTTP requests to remote URLs
	 *
	 * @param string $url		The URL to make an HTTP request to
	 */
	public function __construct($url = null)
	{
		$this->_url = $url;

		$this->_verify_peer = true;
		$this->_verify_host = true;

		$this->_log = '';

		$this->_debug = false;
		$this->_test = false;

		$this->_request_headers = array();
		$this->_return_headers = false;

		$this->_last_request = null;
		$this->_last_response = null;
		$this->_last_duration = 0.0;
	}

	/**
	 * Set the URL
	 *
	 * @param string $url
	 * @return void
	 */
	public function setURL($url)
	{
		$this->_url = $url;
	}

	/**
	 * Get the URL
	 *
	 * @return string
	 */
	public function getURL()
	{
		// @TODO Support for query string args from ->setGETValues()
		return $this->_url;
	}

	public function verifyPeer($yes_or_no)
	{
		$this->_verify_peer = (boolean) $yes_or_no;
	}

	public function verifyHost($yes_or_no)
	{
		$this->_verify_host = (boolean) $yes_or_no;
	}

	public function setHeaders($arr)
	{
		foreach ($arr as $key => $value)
		{
			if (is_numeric($key) and
				false !== ($pos = strpos($value, ':')))
			{
				// 0 => "Header: value" format

				$key = substr($value, 0, $pos);
				$value = ltrim(substr($value, $pos + 1));
			}

			// "Header" => "value" format

			$this->setHeader($key, $value);
		}
	}

	/**
	 * Tell whether or not to return the HTTP response headers
	 *
	 * @param boolean $return
	 * @return void
	 */
	public function returnHeaders($return)
	{
		$this->_return_headers = (boolean) $return;
	}

	public function setHeader($key, $value)
	{
		$this->_request_headers[$key] = $value;
	}

	public function getHeaders($as_combined_array = false)
	{
		if ($as_combined_array)
		{
			$list = array();
			foreach ($this->_request_headers as $key => $value)
			{
				$list[] = $key . ': ' . $value;
			}

			return $list;
		}

		return $this->_request_headers;
	}

	public function getHeader($key)
	{
		if (isset($this->_request_headers[$key]))
		{
			return $this->_request_headers[$key];
		}

		return null;
	}

	public function setRawBody($str)
	{
		$this->_body = $str;
	}

	public function setPOSTValues($arr)
	{
		$this->_post = $arr;
	}

	public function setGETValues($arr)
	{
		$this->_get = $arr;
	}

	/**
	 *
	 *
	 * @return string
	 */
	public function getRawBody()
	{
		if ($this->_body)
		{
			return $this->_body;
		}
		else if (count($this->_post))
		{
			return http_build_query($this->_post);
		}

		return '';
	}

	public function setAuth($user, $pass)
	{
		$this->_username = $user;
		$this->_password = $pass;
	}

	public function GET()
	{
		return $this->_request('GET');
	}

	public function PATCH()
	{
		return $this->_request('PATCH');
	}

	public function POST()
	{
		return $this->_request('POST');
	}

	public function HEAD()
	{
		// @todo
	}

	public function useDebugMode($yes_or_no)
	{
		$prev = $this->_debug;
		$this->_debug = (boolean) $yes_or_no;

		return $prev;
	}

	/**
	 * Get the error number of the last error that occured
	 *
	 * @return integer
	 */
	public function errorNumber()
	{
		return $this->_errnum;
	}

	/**
	 * Get the error message of the last error that occured
	 *
	 * @return string
	 */
	public function errorMessage()
	{
		return $this->_errmsg;
	}

	/**
	 * Get the last raw XML response that was received
	 *
	 * @return string
	 */
	public function lastResponse()
	{
		return $this->_last_response;
	}

	/**
	 * Get the last raw XML request that was sent
	 *
	 * @return string
	 */
	public function lastRequest()
	{
		return $this->_last_request;
	}

	/**
	 *
	 *
	 */
	public function lastDuration()
	{
		return $this->_last_duration;
	}

	public function lastInfo()
	{
		return $this->_last_info;
	}

	/**
	 * Set an error message
	 *
	 * @param integer $errnum	The error number/code
	 * @param string $errmsg	The text error message
	 * @return void
	 */
	protected function _setError($errnum, $errmsg = '')
	{
		$this->_errnum = $errnum;
		$this->_errmsg = $errmsg;
	}

	/**
	 *
	 *
	 *
	 * @param string $message
	 * @param integer $level
	 * @return boolean
	 */
	protected function _log($message)
	{
		if ($this->_debug)
		{
			print($message . "\r\n");
		}

		//
		$this->_log .= $message . "\r\n";

		return true;
	}

	public function resetLog()
	{
		$this->_log = '';
	}

	public function getLog()
	{
		return $this->_log;
	}

	/**
	 * Make an HTTP request
	 *
	 * @param string $method
	 * @return string
	 */
	protected function _request($method)
	{
		$start = microtime(true);

		if (!function_exists('curl_init'))
		{
			die('You must have the PHP cURL extension (php.net/curl) enabled to use this.');
		}

		$this->_log('Using CURL to send request!');
		$return = $this->_requestCurl($method, $errnum, $errmsg);

		if ($errnum)
		{
			$this->_setError($errnum, $errmsg);
		}

		// Calculate and set how long the last HTTP request/response took to make
		$this->_last_duration = microtime(true) - $start;

		return $return;
	}

	protected function _requestCurl($method, &$errnum, &$errmsg)
	{
		$url = $this->getURL();
		$raw_body = $this->getRawBody();

		$headers = $this->getHeaders(true);

		$this->_log('Opening connection to: ' . $url);

		$params = array();

		if ($method == 'POST')
		{
			$headers[] = 'Content-Length: ' . strlen($raw_body);
			$params[CURLOPT_POST] = true;
			$params[CURLOPT_POSTFIELDS] = $raw_body;
		}
		else if ($method == 'PATCH')
		{
			$headers[] = 'Content-Length: ' . strlen($raw_body);
			$params[CURLOPT_CUSTOMREQUEST] = 'PATCH';
			$params[CURLOPT_POSTFIELDS] = $raw_body;
		}

		$query = '';
		if (count($this->_get))
		{
			$query = '?' . http_build_query($this->_get);
		}

		if ($qs = parse_url($url, PHP_URL_QUERY) and
			false !== strpos($qs, ' '))
		{
			$url = str_replace($qs, str_replace(' ', '+', $qs), $url);
		}

		$params[CURLOPT_RETURNTRANSFER] = true;
		$params[CURLOPT_URL] = $url . $query;
		$params[CURLOPT_HTTPHEADER] = $headers;
		$params[CURLOPT_ENCODING] = '';			// This makes it say it supports gzip *and* deflate

		$params[CURLOPT_VERBOSE] = $this->_debug;

		if ($this->_return_headers)
		{
			$params[CURLOPT_HEADER] = true;
		}

		// Some Windows servers will fail with SSL errors unless we turn this off
		if (!$this->_verify_peer)
		{
			$params[CURLOPT_SSL_VERIFYPEER] = false;
		}

		if (!$this->_verify_host)
		{
			$params[CURLOPT_SSL_VERIFYHOST] = 0;
		}

		if ($this->_username and $this->_password)
		{
			$params[CURLOPT_USERPWD] = $this->_username . ':' . $this->_password;
		}

		// Fudge the outgoing request because CURL won't give us it
		$request = '';

		if ($method == 'POST')
		{
			$request .= 'POST ';
		}
		else
		{
			$request .= 'GET ';
		}
		$request .= $params[CURLOPT_URL] . ' HTTP/1.1' . "\r\n";

		foreach ($headers as $header)
		{
			$request .= $header . "\r\n";
		}
		$request .= "\r\n";
		$request .= $this->getRawBody();

		$this->_log('CURL options: ' . print_r($params, true));

		$this->_last_request = $request;
		$this->_log('HTTP request: ' . $request);	// Set as DEBUG so that no one accidentally logs all the credit card numbers...

		$ch = curl_init();
		curl_setopt_array($ch, $params);
		$response = curl_exec($ch);

		/*
		print("\n\n\n" . '---------------------' . "\n");
		print('[[request ' . $request . ']]' . "\n\n\n");
		print('[[resonse ' . $response . ']]' . "\n\n\n\n\n");

		print_r($params);
		print_r(curl_getinfo($ch));
		print_r($headers);
		print("\n" . '---------------------' . "\n\n\n\n");
		*/

		$this->_last_info = curl_getinfo($ch);

		$this->_last_response = 'HTTP ' . $this->_last_info['http_code'] . "\n\n" . $response;
		$this->_log('HTTP response: ' . substr($response, 0, 500) . '...');

		if (curl_errno($ch))
		{
			$errnum = curl_errno($ch);
			$errmsg = curl_error($ch);

			$this->_log('CURL error: ' . $errnum . ': ' . $errmsg);

			return false;
		}

		// Close the connection
		@curl_close($ch);

		return $response;
	}
}
