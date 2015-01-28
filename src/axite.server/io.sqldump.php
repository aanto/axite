<?php

class Axite_io_sqldump extends Axite_Plugin
{
	public function onSystemInit () {
		$this->ds_dispatcher = $this->core->ds_dispatcher;
	}

	public function io_www_intercept_url ($url) {
		if (empty($url[0]) || $url[0] != 'sqldump') return false;

		$this->ds = $url[1];

		header("Content-Type: text/plain; charset=utf-8");
		echo "-- SQL DUMP of ds: $this->ds\n";

		// $this->foundKeys = array();
		$toSqlString = $this->iterateFolder($this->ds);

		echo "\n\n-- Create table \n";
		echo "CREATE TABLE `$this->ds` (entity TEXT, attribute TEXT, value BLOB)";
		// echo $this->makeCreateTableString();

		echo "\n\n-- Insert data \n";
		echo $toSqlString;
		// var_dump($this->foundKeys);

		return true;
	}

	private function iterateFolder ($ds, $entityName = '') {
		$toSqlString = '';
		$data = $this->ds_dispatcher->fulllist("$ds/$entityName/");
		echo "-- $ds/$entityName (". count($data) . ")\n";
		
		// toSql
		foreach ($data as $entity) {
			foreach ($entity as $attribute => $value) {
				$toSqlString .= 'INSERT INTO `'.$this->ds.'` SET entity = "'.addslashes($entity['_entity_key']).'", attribute = "'.addslashes($attribute).'", value = "'.addslashes($value)."\"\n";
			}
		}

		// Iterate subfolders
		foreach ($data as $d) {
			if ($d['_has_childs'] && $d['_entity_key']) {
				echo "-- subfolder {$d['_entity_key']} \n";
				$toSqlString .= $this->iterateFolder($ds, $d['_entity_key']);
			}
		}

		return $toSqlString;
	}

/*	
	private function toSql ($arr) {
		$str = "INSERT INTO `$this->ds` SET ";
		$comma = '';

		foreach ($arr as $key=>$value) {
			if ($key != 'url' && in_array($key, AXITE::$SYSTEM_FIELDS)) continue;

			$str .= "$comma`$key` = \"" . addslashes($value) . "\"";
			$comma = ', ';

			$this->registerKey($key, $value);
		}

		$str .= ";\n\n";
		
		return $str;
	}

	private function registerKey ($key, $value) {
		$types = array('', 'BOOLEAN', 'INT', 'FLOAT', 'TINYTEXT', 'TEXT');

		if (!isset($this->foundKeys[$key])) $this->foundKeys[$key] = $types[0];

		if (!$value || is_bool($value)) {
			$newType = 'BOOLEAN';
		} elseif (!$value || is_numeric($value)) {
			$newType = 'INT';
		} elseif (is_numeric($value) && $value == floatval($value)) {
			$newType = 'FLOAT';
		} elseif (strlen($value) < 250) {
			$newType = 'TINYTEXT';
		} else {
			$newType = 'TEXT';
		}

		$newTypeWeight = array_search($newType, $types);
		$oldTypeWeight = array_search($this->foundKeys[$key], $types);

		if ($newTypeWeight >= $oldTypeWeight) {
			$this->foundKeys[$key] = $newType;
		}
	}

	private function makeCreateTableString () {
		$str = "CREATE TABLE `$this->ds` (";
		$comma = '';

		foreach ($this->foundKeys as $key=>$type) {
			$str .= "$comma$key $type";
			$comma = ', ';
		}

		$str .= ");\n\n";
		return $str;
	}
*/
}