Foscam FI8908W Recorder
=======================

Intro
-----

This is recorder code written in PHP for Foscam FI8908W and similar IP cameras. It's sort of designed to be used out of the box but is mostly to give
you an idea of the protocols for use in your own code.

Features
--------

* Streaming and decoding of JPEGs from camera
* Movement analysis and detection
* Day/night detection
* Recording of JPEG frames
* Fully configurable via config
* Timestamping of images
* Storage of images on S3 or local disk
* Driver based storage backed by [dotsunited/Cabinet](http://github.com/dotsunited/Cabinet)

Installation
------------

* Clone this repo. E.g. git clone git://github.com/phil-lavin/Foscam-FI8908W-Recorder.git
* cd into the clone directory
* Init the submodules. I.e. git submodule update --init
* Copy config.sample.php to config.php
* Edit the config as required

Running
-------

* cd into the clone directory
* php run.php

Output
------

The output you see is the difference between the current frame and the previous frame. When this drops below the threshold in the config (dependant on time of day),
recording will begin. 

Recording will save files to a directory, named with the full date, on your desired storage medium (S3, local disk, etc.). Files are named sequentially.

Converting to Video
-------------------

Because files are named sequentially, you can use ffmpeg to convert images to video. An example of this is:

ffmpeg -r 6 -b 1800 -i %d.jpg video.mp4
