<?php

class Cam {
	// Settings
	protected $ip;
	protected $port;
	protected $user;
	protected $pass;

	// Sockets
	protected $init_sock;
	protected $stream_sock;

	// Buffers
	protected $stream_buffer;
	protected $last_keep_alive;

	// Data
	protected $connect_strings = [
		[
			"\x4d", "\x4f", "\x5f", "\x4f", "\x00", "\x00", "\x00", "\x00",
			"\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00",
			"\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00"
		],
		[
			"\x4d", "\x4f", "\x5f", "\x4f", "\x10", "\x00", "\x00", "\x00",
			"\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00",
			"\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00"
		],
		[
			"\x4d", "\x4f", "\x5f", "\x4f", "\x04", "\x00", "\x00", "\x00",
			"\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x01",
			"\x00", "\x00", "\x00", "\x01", "\x00", "\x00", "\x00", "\x02"
		],
	];
	protected $user_pass_prefix = [
		"\x4d", "\x4f", "\x5f", "\x4f", "\x02", "\x00", "\x00", "\x00",
		"\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x1a",
		"\x00", "\x00", "\x00", "\x1a", "\x00", "\x00", "\x00",
	];
	protected $stream_init = [
		"\x4d", "\x4f", "\x5f", "\x56", "\x00", "\x00", "\x00", "\x00",
		"\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x04",
		"\x00", "\x00", "\x00", "\x04", "\x00", "\x00", "\x00"
	];
	protected $keep_alive = [
		"\x4d", "\x4f", "\x5f", "\x4f", "\xff", "\x00", "\x00", "\x00",
		"\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00",
		"\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00"
	];

	// Constants
	const USER_PAD_TO = 13;
	const PASS_PAD_TO = 13;

	protected function __construct($ip, $port, $user, $pass) {
		// Settings
		$this->ip = $ip;
		$this->port = $port;
		$this->user = $user;
		$this->pass = $pass;

		// Implode the data
		$this->_implode($this->connect_strings);
		$this->_implode($this->stream_init);
		$this->_implode($this->keep_alive);

		// Inject user/pass into connect data
		$this->_inject_user_pass($this->user_pass_prefix, $this->connect_strings, 1);
	}

	public static function forge($ip, $port, $user, $pass) {
		return new static($ip, $port, $user, $pass);
	}

	protected function _implode(&$data) {
		// Handle array of byte arrays
		if (is_array(reset($data))) {
			foreach ($data as &$datum) {
				$datum = implode($datum);
			}
		}
		// Handle regular byte arrays
		else {
			$data = implode($data);
		}
	}

	protected function _inject_user_pass($prefix, &$data, $index) {
		// Start with the prefix
		$u_p_str = implode($prefix);

		// Add username
		$u_p_str .= $this->user;

		// Pad to USER_PAD_TO
		$u_p_str .= str_repeat("\x00", static::USER_PAD_TO - strlen($this->user));

		// Add pass
		$u_p_str .= $this->pass;

		// Pad to PASS_PAD_TO
		$u_p_str .= str_repeat("\x00", static::PASS_PAD_TO - strlen($this->pass));

		// Inject into $data
		array_splice($data, $index, 0, $u_p_str);
	}

	protected function _get_code() {
		// Loop strings and send them in turn
		foreach ($this->connect_strings as $key=>$string) {
			fwrite($this->init_sock, $string);

			// Sleep before we read as the camera can sometimes be a little slow, especially when fragmenting data
			usleep(200000);

			// Read at most 2048 bytes back
			$reply = fread($this->init_sock, 2048);

			// If it's the 4th message (key #3) the last 4 bytes are the code
			if ($key == 3)
				return substr($reply, -4);
		}
	}

	public function connect() {
		// Disconnect if we need to
		$this->disconnect();

		// Get the code using the init data
		$this->init_sock = fsockopen($this->ip, $this->port);
		$code = $this->_get_code();

		// Start the stream using the code from above
		$this->stream_sock = fsockopen($this->ip, $this->port);
		fwrite($this->stream_sock, $this->stream_init.$code);

		// Clear the stream buffer
		$this->stream_buffer = '';

		// Keep alive init
		$this->last_keep_alive = time();

		// Return myself for chaining
		return $this;
	}

	public function disconnect() {
		// Close ALL the sockets
		$this->init_sock and fclose($this->init_sock);
		$this->stream_sock and fclose($this->stream_sock);

		// Return myself for chaining
		return $this;
	}

	public function keep_alive() {
		if ($this->last_keep_alive < time() - 25) {
			if (fwrite($this->init_sock, $this->keep_alive) === false) {
				$this->connect();
			}

			$this->last_keep_alive = time();
		}
	}

	public function get_jpeg() {
		// Reconnect if we need to
		if (!$this->stream_sock || feof($this->stream_sock))
			$this->connect();

		// Keep the stream alive
		$this->keep_alive();

		// Read until we have an image
		while (substr_count($this->stream_buffer, 'MO_V') < 2) {
			$buffer = fread($this->stream_sock, 204800);

			if ($buffer === false)
				$this->connect();

			$this->stream_buffer .= $buffer;
		}

		// Get the image bounds
		$start = strpos($this->stream_buffer, 'MO_V');
		$end = strpos($this->stream_buffer, 'MO_V', $start + 4);
		// Get the image. 36 is the number of bytes of crap before the jpeg
		$img = substr($this->stream_buffer, $start + 36, $end - $start);

		// Remove this image from the buffer
		$this->stream_buffer = substr($this->stream_buffer, $end);

		// Return the image
		return $img;
	}
}



