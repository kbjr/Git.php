<?php

/*
 * test-suite.php
 *
 * The Git.php test suite
 *
 * @package    Git.php
 * @version    0.1.1-a
 * @author     James Brumond
 * @copyright  Copyright 2010 James Brumond
 * @license    http://github.com/kbjr/Git.php
 * @link       http://code.kbjrweb.com/project/gitphp
 */

define("DIR", dirname(__FILE__));

// include the git library
require DIR."/../Git.php";

$Git_Tests = array(
	
	'Git' => function() {
		$return = null;
		$repo = new GitRepo();
		$found = $repo->test_git();
		if ($found) {
			$return = array(0, "Git was located and tested successfully");
		} else {
			$return = array(1, "Git could not be found at the default location");
		}
		return $return;
	},
	
	'Git::create()' => function() {
		$return = null;
		$repo = Git::create(DIR."/create");
		if (! Git::is_repo($repo)) {
			$return = array(2, "Git::create() failed to produce expected output.");
		} else {
			$return = array(0, "Git::create() executed successfully");
		}
		return $return;
	},
	
	'Git::create([ $source ])' => function() {
		$return = null;
		$repo = Git::create(DIR."/createfrom", DIR."/test.git");
		if (! Git::is_repo($repo)) {
			$return = array(2, "Git::create([ \$source ]) failed to produce expected output.");
		} else {
			$return = array(0, "Git::create([ \$source ]) executed successfully");
		}
		return $return;
	},
	
	'Git::open()' => function() {
		$return = null;
		$repo = Git::open(DIR."/test.git");
		if (! Git::is_repo($repo)) {
			$return = array(2, "Git::open() failed to produce expected output.");
		} else {
			$return = array(0, "Git::open() executed successfully");
		}
		return $return;
	}

);

class Git_TestSuiteControl {

	protected $warnings = 0;
	protected $errors   = 0;
	
	public static function rm($dir) {
        if (! file_exists($dir)) return true;
        if (! is_dir($dir)) return unlink($dir);
        $items = scandir($dir);
        closedir(opendir($dir));
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            if (! self::rm("$dir/$item")) return false;
        }
        return rmdir($dir);
    }

	public function __construct() {
		$this->output("Starting Git.php Test Suite.");
	}

	public function output($msg) {
		echo "$msg\n";
	}
	
	public function warning($msg) {
		$this->warnings++;
		$this->output("Warning: $msg");
	}
	
	public function error($msg) {
		$this->errors++;
		$this->output("Error: $msg");
	}
	
	public function finish() {
		$this->cleanup();
		$this->output("\n\n".str_repeat('-', 30));
		$this->output("Tests Complete. Results:");
		$this->output("Errors: ".$this->errors."  Warnings: ".$this->warnings);
	}
	
	public function cleanup() {
		self::rm(DIR."/create");
		self::rm(DIR."/createfrom");
	}
	
	public function run_test($name, $callback) {
		$this->output("\nTesting $name.");
		$this->cleanup();
		try {
			$oldReport = error_reporting(0);
			set_error_handler(function($n, $str) {
				throw new Exception($str);
			});
			$result = $callback();
			error_reporting($oldReport);
			restore_error_handler();
		} catch (Exception $e) {
			$this->error("Test case threw an exception:\n  ".implode("\n  ", explode("\n", $e->getMessage())));
			return;
		}
		switch ($result[0]) {
			case 0:
				$this->output($result[1]);
			break;
			case 1:
				$this->warning($result[1]);
			break;
			case 2:
				$this->error($result[1]);
			break;
		}
	}

}

// define the test class
class Git_TestSuite {

	public static function run() {
		
		$test = new Git_TestSuiteControl();
		
		foreach ($GLOBALS['Git_Tests'] as $name => $callback) {
			$test->run_test($name, $callback);
		}
		
		$test->finish();
		unset($test);
		
	}

}

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) {
	header("Content-Type: text/plain");
	Git_TestSuite::run();
}

/* End Of File */
