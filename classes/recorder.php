<?php

class Recorder {
	// Settings
	protected $record_time;
	protected $dir;

	// Data
	protected $started = false;
	protected $date_dir;
	protected $frame = 0;
	protected $last_run = 0;

	protected function __construct($record_time, $dir) {
		$this->record_time = $record_time;
		$this->dir = $dir;
	}

	public static function forge($record_time, $dir = 'out') {
		return new static($record_time, $dir);
	}

	protected function _file($inc) {
		return ($this->dir.'/'.$this->date_dir.'/'.($inc?$this->frame++:$this->frame).'.jpg');
	}

	public function start() {
		$this->started = time();
		$this->date_dir = date('Y-m-d_H:i:s', $this->started);
		$this->frame = 0;

		mkdir(dirname($this->_file(false)), 0777, true);
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
		// Stop if we're over the record time
		if ($this->started < time() - $this->record_time) {
			$this->stop();
			return;
		}

		$img->writeImage($this->_file(true));
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