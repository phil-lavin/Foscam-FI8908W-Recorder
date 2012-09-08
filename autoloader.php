<?php

class Autoloader {
	public static function load($class) {
		$classes = include('sources.php');

		$file = str_replace('\\', '/', $class).'.php';

		foreach ($classes as $dir) {
			if (file_exists($dir.$file)) {
				require_once($dir.$file);
				return true;
			}
		}

		return false;
	}
}
