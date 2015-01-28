<?php class debug {
	private static $log;

	static function log($message, $type = 'notice') {
		self::$log[] = array(
			'message' => $message,
			'type' => $type,
			'time' => getscriptmicrotime(),
			'backtrace' => debug_backtrace(),
			'memory' => memory_get_usage()
		);
	}

	static function fetch_trace($backtrace) {
		static $prev;

		$backtrace = array_reverse($backtrace);
		array_pop($backtrace);

		$out = '<pre>';
		foreach ($backtrace as $key=>$i) {
			$out .=
				(((!isset($prev[$key]) || $i != $prev[$key])) ? '<p>' : '<p class="same">') .
				'<tt>' . str_repeat('&middot;', $key) . ' </tt>' .
				((isset($i['class']) && $i['class'] !== get_class($i['object'])) ? '<b>'.$i['class'].'</b> ' : '') .
				(isset($i['object']) ? get_class($i['object']) : '&empty;') . ' ' .
				(isset($i['type']) ? $i['type'] : '') .
				(isset($i['file'], $i['line']) ? (" <span title='" . str_replace($_SERVER['DOCUMENT_ROOT'], '', $i['file']) . " &nbsp; {$i['line']}'>"):("<span title='unknown'>")) .
				$i['function'] . "</span>" .
				'(' . self::fetch_trace_args($i['args']) . ')' .
				"</p>";
		}
		$out .= '</pre>';

		$prev = $backtrace;
		return $out;
	}

	static function fetch_trace_args($args) {
		$out = array();
		foreach ($args as $arg) {
			if (is_object($arg)) {
				$type = gettype($arg);
				$class = get_class($arg);
				$out[] = "<b>$type $class</b>";
			} else {
				$type = gettype($arg);
				if ($type == 'string') {
					$substr = substr($arg, 0, 20);
					$hellip = ($substr == $arg) ? '' : '&hellip;';
					$arg = str_replace("'", "&quot;", $arg);
					$out[] = "\"<var title=\"$arg\">$substr</var>$hellip\"";
				} elseif ($type == 'array') {
					$keys = implode(', ', array_keys($arg));
					$out[] = "<b title='[$keys]'>$type</b>";
				} else {
					$out[] = "<b>$type</b> <var>$arg</var>";
				}
			}
		}
		return implode(', ', $out);
	}

	static function display_log() {
		echo '<div class="debug_log"><hr><h3>Debug Log:</h3>';
		echo '<table>';
			echo '<tr>
			<th>time</th>
			<th>memory</th>
			<th>trace</th>
			<th>type</th>
			<th>message</th>
			</tr>';
		foreach(self::$log as $i) {
			echo "<tr class='debug_{$i['type']}'>";
			echo '<td>' . number_format($i['time'], 3) . '</td>';
			echo '<td>' . ceil($i['memory']/1024) . '</td>';
//			echo '<td>&mdash;</td>';
			echo '<td>' . self::fetch_trace($i['backtrace']) . '</td>';
			echo "<td>" . $i['type'] . '</td>';
			echo '<td>' . nl2br($i['message']) . '</td>';
			echo '</tr>';
		}
		echo '</table>';

		$total = getscriptmicrotime();
		echo '<p>Total execution time: ' . $total . ' (' . number_format(1000/$total) . ' requsets per second)' . '</p>';
		echo '<p>Peak memory usage: ' . memory_get_peak_usage() . '</p>';

		echo '</pre></div>';
	}
}


function axite_exception_handler($e) {
	echo "<h4 class='error'>AXITE exception ".$e->getCode()."</h4>";
}

function axite_exception_handler_debug($e) {
	echo "<h4 class='error'>AXITE exception ".$e->getCode()."</h4>";
	echo "<p>". nl2br($e->getMessage()) ."</p>";
	echo "<pre>". $e->getTraceAsString() ."</pre>";
}

function axite_error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
	echo "<h4 class='error'> Error $errno: $errstr $errfile [$errline]</h4>";
	echo "<pre>". trace() ."</pre>";
}

set_error_handler('axite_error_handler', E_ALL ^ E_NOTICE);

function dump(&$obj) {
	$dump = "<pre class='debug_dump'>";
	if(is_object($obj)) $props = get_object_vars($obj);
	if(is_array($obj)) $props = $obj;
	if($props) foreach($props as $key=>$i) {
		if (is_object($i)) {
			$class = get_class($i);
			$dump .= "<p>$key => (object $class)</p>";
		} elseif (is_array($i)) {
			$dump .= "<p>'$key' => (" . dump($i) . ")</p>";
		} else {
			$dump .= "<p>$key => $i</p>";
		}
	}
	$dump .= "</pre>";

	return $dump;
}

function trace() {
	echo "<table class='debug_backtrace'>";
	$trace = debug_backtrace(); array_shift($trace);
	foreach ($trace as $key=>$i) {
		if (!empty($i['object'])) {
			$dump = dump($i['object']);
		} else {
			$dump = '';
		}

		$args = '';
		if (!empty($i['args'])) foreach ($i['args'] as $a) {
			if (!is_object($a)) {
				if (is_array($a)) {
					$args .= "<p>Array[".count($a)."]</p>";
				} else {
					$args .= "<p>$a</p>";
				}
			} else {
				$class = get_class($a);
				$args .= "<p>(object $class)</p>";
			}
		}

		// Fuck notices
		if (!isset($i['file'])) $i['file'] = null;
		if (!isset($i['line'])) $i['line'] = null;
		if (!isset($i['class'])) $i['class'] = null;
		if (!isset($i['type'])) $i['type'] = null;
		if (!isset($i['function'])) $i['function'] = null;

		echo "<tr>
			<td>{$i['file']}</td>
			<td>{$i['line']}</td>
			<td>{$i['class']} {$i['type']} {$i['function']}</td>
			<td>$args</td>
			<td>$dump</td>
		</tr>";
	}
	echo "</table>";
}

?>