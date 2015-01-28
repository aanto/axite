<?php

class Axite_driver_mysql_eav extends Axite_DS_Plugin
{
	function onSystemInit() {
		include_once 'driver.mysql.php';
		// We dont want to expose our mysql driver on whole system
		$this->db = new Axite_driver_mysql($this->core, $this, $this->config);
		$this->db->onSystemInit();
	}

	function list_ds ($result) {
		return $this->db->q("SHOW TABLES", 'col', 'Tables_in_oberhlib');
	}

	function ds_get ($qpath) {
		list($table, $url, $fields) = $this->breakQpath($qpath);
		if ($fields === '*') {
			$mode = 'row';
		} else {
			$mode = 'row col';
		}

		$data = $this->db->q("SELECT $fields FROM $table WHERE `url` = '$url'", $mode);
		if (is_array($data)) $data['_entity_key'] = "$table/$url";
		return $data;
	}

	function ds_set ($qpath, $value) {
		list($table, $url, $fields) = $this->breakQpath($qpath);
		return $this->db->q("UPDATE $table SET `$fields` = '$value' WHERE `url` = '$url'");
	}

	function ds_setData ($inData) {
		$result = true;
		foreach ($inData as $key => $value) {
			$result = $result && $this->ds_set($key, $value);
		}
		return $result;
	}

	function ds_delete ($qpath) {
		list($table, $url, $fields) = $this->breakQpath($qpath);
		return $this->db->q("DELETE FROM $table WHERE `url` = '$url'");
	}

	function ds_fulllist ($qpath) {
		list($table, $url, $fields) = $this->breakQpath($qpath);
		$urlslash = $url ? (rtrim($url,"/") . "/") : "";
		$data = $this->db->q("SELECT $fields FROM $table WHERE `url` LIKE '$urlslash%' AND `url` NOT LIKE '$urlslash%/%'");
		foreach ($data as &$d) {
			$d['_entity_key'] = "$table/" . $d['_entity_key'];
			$d['_has_childs'] = $this->db->q("SELECT url FROM $table WHERE `url` LIKE '{$d['_entity_key']}/%' LIMIT 1", 'row col');
		}
		return $data;
	}

	private function breakQpath($qpath) {
		// $qpath = ds_name/path/to/file:path/to/json
		$qpath_parts = explode(':', $qpath, 2);
		// $key_parts = isset($qpath_parts[1]) ? explode("/", $qpath_parts[1]) : array();
		$fields = isset($qpath_parts[1]) ? $qpath_parts[1] : '*';
		$path_parts = isset($qpath_parts[0]) ? explode("/", $qpath_parts[0]) : array();
		$table = array_shift($path_parts);
		$url = implode("/", $path_parts);
		
		return array($table, $url, $fields);
	}
}