<?php


class Axite_io_debug extends Axite_Plugin
{
	public function output_ajax_footer () {
		if (!DEBUG) return;
		
		echo "// System log:\n";
		foreach (debug::log as $key => $value) {
			extract($value);
			echo "// $time $type: $text\n";
		}
	}

	public function output_footer () {
		if (!DEBUG) return;

		?><div class="axite_log_window"><h4>System log:</h4><?
		foreach (debug::log as $key => $value) {
			extract($value);
			echo "<p><span class='axite_log_time'>$time</span> <span class='axite_log_$type'>$text</span></p>";
		}
		?></div><?
	}
}