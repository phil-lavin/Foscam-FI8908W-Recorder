<?php

class ImgTimeline {
	// The metric we compare by
	protected $diff_metric = Imagick::METRIC_UNDEFINED;

	// The metric's movement threshold
	protected $movement_threshold = 0.97; // 0.97 daytime. 0.91 night time :(

	// The % of frames that need to be below the threshold to register as movement
	protected $movement_frame_percentage = 50;

	// Settings
	protected $size;
	protected $debug = false;

	// The timeline
	protected $timeline = array();
	protected $prev_img = null;

	// Stamp position constants
	const TOP_LEFT = 1;
	const BOTTOM_LEFT = 2;
	const TOP_RIGHT = 4;
	const BOTTOM_RIGHT = 8;

	protected function __construct($size) {
		$this->size = $size;
	}

	public static function forge($size) {
		return new static($size);
	}

	public function set_diff_metric($metric) {
		$this->diff_metric = $metric;

		return $this;
	}

	public function set_movement_threshold($mt) {
		$this->movement_threshold = $mt;

		return $this;
	}

	public function set_movement_frame_percentage($mfp) {
		$this->movement_frame_percentage = $mfp;

		return $this;
	}

	public function set_size($size) {
		$this->size = $size;

		return $this;
	}

	public function set_debug($debug) {
		$this->debug = $debug;

		// Return myself for chaining
		return $this;
	}

	public function stamp_last_image($text, $position) {
		// Get the last image and its geometry
		$img = end($this->timeline)['image'];
		$geo = $img->getImageGeometry();

		// Define the text data
		$draw = new ImagickDraw();
		$draw->setFillAlpha(1);
		$draw->setFontSize(12);
		$draw->setTextUnderColor('#ffffff');

		// Get the metrics of the text in the font
		$text_metrics = $img->queryFontMetrics($draw, $text);

		// Calc the position(s)
		$positions = array();
		if ($position & static::TOP_LEFT) {
			$positions[] = array('x'=>10, 'y'=>10 + $text_metrics['textHeight']);
		}
		if ($position & static::BOTTOM_LEFT) {
			$positions[] = array('x'=>10, 'y'=>$geo['height'] - $text_metrics['textHeight']);
		}
		if ($position & static::TOP_RIGHT) {
			$positions[] = array('x'=>$geo['width'] - $text_metrics['textWidth'] - 10, 'y'=>10 + $text_metrics['textHeight']);
		}
		if ($position & static::BOTTOM_RIGHT) {
			$positions[] = array('x'=>$geo['width'] - $text_metrics['textWidth'] - 10, 'y'=>$geo['height'] - $text_metrics['textHeight']);
		}

		// Add the text
		foreach ($positions as $position) {
			$img->annotateImage($draw, $position['x'], $position['y'], 0, $text);
		}

		// Return myself for chaining
		return $this;
	}

	public function is_greyscale() {
		if ($this->prev_img) {
			$test_img = (clone $this->prev_img);
			$test_img->setColorspace(Imagick::COLORSPACE_HSB);
			return $test_img->getImageChannelMean(Imagick::COLOR_GREEN)['mean'] < 22000;
		}

		return false;
	}

	public function add_raw_image($img) {
		// Make an image magick image from the raw data
		$img_imagick = new Imagick();
		$img_imagick->readImageBlob($img);

		// Add it
		$this->add_imagick_image($img_imagick);

		// Return myself for chaining
		return $this;
	}

	public function add_imagick_image(Imagick $img) {
		static $cnt = 0;

		// Remove one from the array if at max size
		if (count($this->timeline) >= $this->size)
			array_shift($this->timeline);

		// Get the diff with the previous, if there is one
		if ($this->prev_img) {
			try {
				$diff = $this->prev_img->compareImages($img, $this->diff_metric)[1];
			}
			catch (ImagickException $e) {
				$diff = 1; // Skip if we failed to compare
			}
		}
		else {
			$diff = null;
		}

		echo '   '.$diff."\r";

		// Store the original
		$this->prev_img = $img;

		// Add a clone to the array so we can annotate it
		$this->timeline[] = array('image'=>(clone $img), 'diff'=>$diff);

		// Add diff to image if in debug mode
		if ($diff && $this->debug)
			$this->stamp_last_image($diff, static::TOP_LEFT | static::BOTTOM_RIGHT);

		// Return myself for chaining
		return $this;
	}

	public function has_movement() {
		// The number of frames we require to be below the movement threshold to register as movement
		$required_frames = round($this->size * ($this->movement_frame_percentage / 100), 0);

		// Count frames below threshold
		$count = 0;
		foreach ($this->timeline as $entry) {
			if ($entry['diff'] < $this->movement_threshold) {
				$count++;
			}
		}

		// Return yay or nay
		return $count >= $required_frames;
	}

	public function get_images() {
		$out = array();

		// Loop and add the images
		foreach ($this->timeline as $datum) {
			$out[] = $datum['image'];
		}

		return $out;
	}

	public function get_last_image() {
		return end($this->timeline)['image'];
	}
}
