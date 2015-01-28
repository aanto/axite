<?php

function ifset($arr, $key, $def = null) {
	return isset($arr[$key]) ? $arr[$key] : $def;
}

function explode_set($delimiter, $string, $limit) {
	return array_pad(explode($delimiter, $string, $limit), $limit, null);
}

function getscriptmicrotime ($n = 1000) {
	static $started = null;
	if (!$started) $started = getmicrotime();
	return ((int)((getmicrotime()-$started)*$n)/$n);
}

function getmicrotime () {
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
}

function strlchr ($haystack, $needle) {
    $pos = strrpos($haystack, $needle);
    if($pos === false) {
        return $haystack;
    }
    return substr($haystack, 0, $pos + 1);
}

function array_inject(&$haystack, $parts, $value) {
	if (is_string($parts)) $parts = explode("/", $parts);

	$d =& $haystack;

	if (empty($value)) {
		// Delete
		foreach ($parts as $part) {
			if (!isset($d[$part])) break;
			$dPrev =& $d;
			$d =& $d[$part];
		}
		unset($dPrev[$part]);
	} else {
		while ($part = array_shift($parts)) {
			if (!isset($d[$part])) $d[$part] = array();
			$d =& $d[$part];
		}
		$d = $value;
	}
}

function json_last_error_msg () {
    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            return;
        case JSON_ERROR_DEPTH:
            return ' - Maximum stack depth exceeded';
        case JSON_ERROR_STATE_MISMATCH:
            return ' - Underflow or the modes mismatch';
        case JSON_ERROR_CTRL_CHAR:
            return ' - Unexpected control character found';
        case JSON_ERROR_SYNTAX:
            return ' - Syntax error, malformed JSON';
        case JSON_ERROR_UTF8:
            return ' - Malformed UTF-8 characters, possibly incorrectly encoded';
        default:
            return ' - Unknown error';
    }

}

// i18n
function i($str) {
	global $I18N_TABLE;
	if (empty($I18N_TABLE)) return $str;

	$tr = strtolower($str);
	// $lastChar = substr($tr, -1);
	$c = substr($str, 0, 1);

	// if (lastChar.match(/\W/)) {
		// tr = tr.slice(0, -1);
	// } else {
		// lastChar = '';
	// }

	$tr = isset($I18N_TABLE[$tr]) ? $I18N_TABLE[$tr] : null;
	if (!$tr) return $str;

	if ($c === ucfirst($c)) {
		$tr = ucfirst($tr);
	}

	return $tr; // + lastChar;

}