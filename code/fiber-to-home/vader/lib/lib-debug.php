<?php

/*
 * get_elapsed_debug_time
 *
 * @return	string	Time in seconds since last call to this function
 *
 * First call initializes the timer, and returns 0.  All subsequent calls return the elapsed time since last call.
 *
 */
function get_elapsed_debug_time() {
	static $timerGlobal;
	if (empty($timerGlobal)) {
		$timerGlobal = microtime(true);
		return "0.0";
	} else {
		return (string)( microtime(true) - $timerGlobal );
	}
}
