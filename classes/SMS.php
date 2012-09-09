<?php

class SMS {
	protected $username;
	protected $password;
	protected $simulation_mode = false;

	protected static $api_version = 2.1;
	protected static $routine_type = 'broadcast';
	protected static $url = 'https://www.tm4b.com/client/api/http.php';

	protected function __construct($username, $password) {
		$this->username = $username;
		$this->password = $password;
	}

	public static function forge($username, $password) {
		return new static($username, $password);
	}

	public function set_simulation_mode($mode = true) {
		$this->simulation_mode = $mode;

		return $this;
	}

	public function send($to, $from, $message) {
		// Build and send request
		$data = array(
			'username'=>$this->username,
			'password'=>$this->password,
			'version'=>static::$api_version,
			'type'=>static::$routine_type,
			'to'=>$to,
			'from'=>$from,
			'msg'=>$message,
			'sim'=>$this->simulation_mode ? 'yes' : 'no',
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, static::$url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($ch);

		curl_close($ch);

		// Attempt to parse results
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($result);

		// Failed
		if (!$xml)
			throw new SMS_Failed_Exception('Failed to send SMS. Error reported was: '.$result);

		// Success (possibly)
		return (bool)$xml->recipients;
	}
}

class SMS_Failed_Exception extends \Exception {}
