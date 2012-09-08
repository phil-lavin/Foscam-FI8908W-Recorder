<?php

// Classes
require_once('classes/cam.php');
require_once('classes/img_timeline.php');
require_once('classes/recorder.php');

$cam = Cam::forge('172.20.0.15', 80)->connect();
$timeline = ImgTimeline::forge(6)->set_debug(true);
$recorder = Recorder::forge(30);

$counter = 0;
$greyscale = false;

while (1) {
	$timeline->add_raw_image($cam->get_jpeg());
	$timeline->stamp_last_image(date('r'), ImgTimeline::TOP_RIGHT | ImgTimeline::BOTTOM_LEFT);

	// Set the thresholds if it's day or night
	$counter++;
	if (($counter != 1 && $counter < 10) || ($counter % 10 == 0)) {
		// Night
		if ($timeline->is_greyscale()) {
			$timeline->set_movement_threshold(0.94);

			if (!$greyscale) {
				var_dump(date('r').' Night!');
			}

			$greyscale = true;
		}
		// Day
		else {
			$timeline->set_movement_threshold(0.97);

			if ($greyscale) {
				var_dump(date('r').' Day!');
			}

			$greyscale = false;
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
