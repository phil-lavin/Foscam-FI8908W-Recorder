<?php

class Recorder {
	// Settings
	protected $record_time;

	// Data
	protected $started = false;
	protected $date_dir;
	protected $frame = 0;
	protected $last_run = 0;

	// Storage
	protected $storage;

	protected function __construct($record_time, $storage) {
		$this->record_time = $record_time;
		$this->storage = $storage;
	}

	public static function forge($record_time, \DotsUnited\Cabinet\Adapter\AdapterInterface $storage) {
		return new static($record_time, $storage);
	}

	protected function _file($inc) {
		return $this->date_dir.'/'.($inc?$this->frame++:$this->frame).'.jpg';
	}

	public function start() {
		$this->started = time();
		$this->date_dir = date('Y-m-d_H:i:s', $this->started);
		$this->frame = 0;

		// Add date_dir to metadata file
		try {
			$meta = $this->storage->read('.meta');
		}
		catch (\Exception $e) {
			$meta = null;
		}

		$meta = $meta ? json_decode($meta, true) : array('recordings'=>array());

		// Modify and write
		$meta['recordings'][] = $this->date_dir;

		try {
			$this->storage->write('.meta', json_encode($meta));
		}
		catch (\Exception $e) {}
	}

	public function stop() {
		$this->started = false;
		$this->last_run = time();
	}

	public function restart() {
		$this->started = time();
	}

	public function started() {
		return $this->started !== false;
	}

	public function add_frame(Imagick $img) {
		if (!$this->started())
			return;

		// Stop if we're over the record time
		if ($this->started < time() - $this->record_time) {
			$this->stop();
			return;
		}

		try {
			$this->storage->write($this->_file(true), $img->getImageBlob());
		}
		catch (\Exception $e) {}
	}

	public function add_timeline(ImgTimeline $timeline) {
		foreach ($timeline->get_images() as $img) {
			$this->add_frame($img);
		}
	}

	public function last_run() {
		return $this->last_run;
	}
}
