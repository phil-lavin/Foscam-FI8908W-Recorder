<?php

// Autoloader
require_once('autoloader.php');
spl_autoload_register(array('Autoloader', 'load'));

// Config
$config = require_once('config.php');

// Storage
require_once 'libraries/AWSSDKforPHP/sdk.class.php';
$storage = \DotsUnited\Cabinet\Cabinet::factory($config['cabinet_adapter'], $config[$config['cabinet_settings']]);


// The business
$cam = Cam::forge($config['cam_ip'], $config['cam_port'])->connect();
$timeline = ImgTimeline::forge($config['timeline_size'])->set_debug(true);
$recorder = Recorder::forge($config['record_min_length'], $storage);

$counter = 0;

while (1) {
	$timeline->add_raw_image($cam->get_jpeg());
	$timeline->stamp_last_image(date('r'), ImgTimeline::TOP_RIGHT | ImgTimeline::BOTTOM_LEFT);

	// Set the thresholds if it's day or night
	$counter++;
	if (($counter != 1 && $counter < 10) || ($counter % 10 == 0)) {
		// Night
		if ($timeline->is_greyscale()) {
			$timeline->set_movement_threshold(1);
		}
		// Day
		else {
			$timeline->set_movement_threshold(0.97);
		}
	}

	if (!$recorder->started() && $timeline->has_movement()) {
		// Restart if we've been recording in the last 30 seconds
		if ($recorder->last_run() > time() - 30) {
			$recorder->restart();
		}
		else {
			$recorder->start();
		}

		$recorder->add_timeline($timeline);
	}
	elseif ($recorder->started()) {
		$recorder->add_frame($timeline->get_last_image());

		// Restart the timer if there's still movement
		if ($timeline->has_movement()) {
			$recorder->restart();
		}
	}
}
