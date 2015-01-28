<?php

class Axite_driver_filestore extends Axite_DS_Plugin
{
	private $childFolderPrefix = "-";
	private $saveCache = Array();

	function onSystemInit() {
		$this->root = $_SERVER['DOCUMENT_ROOT'] . $this->config['dir_data'];
	}

	function onSystemFinish() {
		$this->save_data();
	}

	function ds_get ($entity, $attribute) {
		// try to get from saveCache
		if (isset($this->saveCache[$entity], $this->saveCache[$entity][$attribute])) {
			return $this->saveCache[$entity][$attribute];
		}

		$key_parts = explode('/', $entity);
		if (end($key_parts) == '*') {
			// return fullist instead of one entity
			array_pop($key_parts);
			$d = $this->ds_fulllist(implode('/', $key_parts));
		} else {
			// read file
			$data = $this->read_file($entity);

			// reduce array to requested attribute depth
			$d =& $data;
			$parts = explode("/", $attribute);
			while ($part = array_shift($parts)) {
				$d =& $d[$part];
			}
		}

		if (DEBUG) debug::log("ds_filestore get $entity:$attribute = Array of " . count($d), "ds_get");
		return $d;
	}

	function ds_setIfUnset ($entity, $attribute, $value) {
		if (!$this->ds_get($entity, $attribute)) {
			$this->ds_set ($entity, $attribute, $value);
		}
	}

	function ds_set ($entity, $attribute, $value) {
		$parts = explode("/", $attribute);

		if (count($parts) === 1) {
			// Save scalar value
			$saveValue = $value;
		}
		elseif (count($parts) > 1) {
			// Save complex value
			$attribute = array_shift($parts);
			$data = $this->ds_get($entity, $attribute);
			array_inject($data, $parts, $value);
			$saveValue = $data;
		}
		elseif (count($parts) < 1) {
			throw "ds_filestore::ds_set No attribute specified";
		}

		$this->saveCache[$entity][$attribute] = $saveValue;
		if (DEBUG) debug::log("ds_filestore set: $entity:$attribute = $saveValue", "ds_set");
		return true;
	}

	function ds_delete ($entity, $attribute = null) {
		if ($attribute) {
			// $parts = explode("/", $attribute);
			// if (count($parts) > 1) {
			// 	// Delete complex value
			// 	$attribute = array_shift($parts);
			// 	$data = $this->ds_get($entity, $attribute);
			// 	array_inject($data, $parts, AXITE::UNSET_VALUE);
			// }
			$this->saveCache[$entity][$attribute] = AXITE::UNSET_VALUE;
		} else {
			$this->saveCache[$entity] = AXITE::UNSET_VALUE;
		}

		if (DEBUG) debug::log("ds_filestore delete: $entity:$attribute", "ds");
		return true;
	}

	function ds_keys ($entity, $attribute = null) {
		// echo "keys";
		// var_dump($entity);
		// var_dump($attribute);
		if ($attribute === null) {
			return $this->ds_keys_e($entity);
		} else {
			return $this->ds_keys_ea($entity, $attribute);
		}
	}

	private function ds_keys_ea ($entity, $attribute) {
		$subKeys = $this->ds_get($entity, $attribute);
		$result = array();
		// var_dump($entity, $attribute);
		// var_dump($subKeys);
		foreach ($subKeys as $subKey => $subValue) {
			$result["$entity:$attribute/$subKey"] = true;
		}
		// var_dump($subKeys);
		// var_dump($result);
		return array_keys($result);
	}

	// private function ds_keys_e () {
	// 	$entity = trim($entity, '/');
	// 	static $cache = array();
	// 	if (isset($cache[$entity])) {
	// 		if (DEBUG) debug::log("ds_filestore [cache hit] keys $entity", "ds_get");
	// 		return $cache[$entity];
	// 	}

	// 	$dirname = "$this->root/" . trim($this->pathfileWCP("/$entity/"), "/");
	// 	if (!is_dir($dirname)) return array();

	// 	$items = array();
	// 	$sortKey = null;

	// 	$filelist = scandir($dirname);
	// 	$filelist = array_diff($filelist, array(".", ".."));

	// 	foreach ($filelist as $file) {
	// 		if (is_file("$dirname/$file")) {
	// 			$item = "$entity/$file";
	// 			$items[] = $item;
	// 		}
	// 	}

	// 	if (DEBUG) debug::log("ds_filestore fulllist $entity", "ds_get");

	// 	$cache[$entity] = $items;

	// 	return $items;
	// }

	function ds_fulllist ($entity) {
		$entity = trim($entity, '/');
		static $cache = array();
		if (isset($cache[$entity])) {
			if (DEBUG) debug::log("ds_filestore [cache hit] fulllist $entity", "ds_get");
			return $cache[$entity];
		}

		$dirname = "$this->root/" . trim($this->pathfileWCP("/$entity/"), "/");
		if (!is_dir($dirname)) return array();

		$items = array();
		$sortKey = null;

		$filelist = scandir($dirname);
		$filelist = array_diff($filelist, array(".", ".."));

		foreach ($filelist as $file) {
			if (is_file("$dirname/$file")) {
				$item = $this->read_file("$entity/$file");
				$items[] = $item;

				// Auto-determine sort key
				if (!$sortKey && !empty($item['name']    )) $sortKey = 'name';
				if (             !empty($item['priority'])) $sortKey = 'priority';
			}
		}

		if (DEBUG) debug::log("ds_filestore fulllist $entity", "ds_get");

		if($sortKey) $this->sort($items, $sortKey);

		$cache[$entity] = $items;

		return $items;
	}

	private function pathfileWCP ($pathfile) {
		$parts = explode('/', $pathfile);
		for($i=0; $i<count($parts)-1; $i++) {
			if ($parts[$i] != "") $parts[$i] = $this->childFolderPrefix . $parts[$i];
		}
		return implode('/', $parts);
	}

	private function sort (&$items, $key = 'priority') {
		// we use lambda here
		usort($items, function ($x, $y) use ($key) {
			if (!isset($x[$key])) return -1;
			if (!isset($y[$key])) return 1;
			$x1 = is_numeric($x[$key]) ? intval($x[$key]) : $x[$key];
			$y1 = is_numeric($y[$key]) ? intval($y[$key]) : $y[$key];
			// echo "{$x[$key]} vs {$y[$key]}<br/>";
			if ($x1 == $y1) return 0;
			if ($x1 <  $y1) return -1;
			if ($x1 >  $y1) return 1;
		});
	}

	private function read_file ($entity) {
		static $cache;
		$physicalPath = $this->root . '/' . $this->pathfileWCP($entity);

		if (isset($cache[$physicalPath])) {
			if (DEBUG) debug::log("ds_filestore [cache hit] read_file $physicalPath", "ds");
			return $cache[$physicalPath];
		} else {
			if (DEBUG) debug::log("ds_filestore read_file $physicalPath", "ds");
			if (file_exists($physicalPath)) {
				$json = file_get_contents($physicalPath);
				$data = json_decode($json, true);
				$data['_is_real'] = true;
			} else {
				$data = Array();
				$data['_is_real'] = false;
			}

			$data['_has_childs'] = is_dir("$this->root/" . $this->pathfileWCP("$entity/"));
			$data['_entity_key'] = trim($entity , '/');
			$data['_origin_ds'] = $this->get_ds_name();

			$cache[$physicalPath] = $data;
			return $data;
		}
	}

	private function delete_file ($entity) {
		if (!$entity) return false;

		$physicalPath = $this->root . '/' . $this->pathfileWCP($entity);
		// remove file itself
		if (is_file($physicalPath)) {
			unlink($physicalPath);
		}

		// remove childs folder
		$childs_dirname = $this->root . '/' . $this->pathfileWCP($entity.'/');
		if (is_dir($childs_dirname)) {
			rmdir($childs_dirname);
		}

		return !is_file($physicalPath);
	}

	private function save_data () {
		foreach ($this->saveCache as $entity => $e) {
			$pathfileWCP = $this->pathfileWCP($entity);

			// delete empty files
			if ($e === AXITE::UNSET_VALUE) {
				$this->delete_file($entity);
				continue;
			}

			// read
			$data = $this->read_file($entity);

			// merge
			foreach ($e as $attribute => $value) {
				if ($value === AXITE::UNSET_VALUE) {
					unset($data[$attribute]);
				} else {
					$data[$attribute] = $value;
				}
			}

			// unset system fields
			foreach (AXITE::$SYSTEM_FIELDS as $attribute) {
				unset($data[$attribute]);
			}

			// save
			$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			$this->ensure_branch(strlchr('/'.$pathfileWCP, '/'));
			file_put_contents("$this->root/$pathfileWCP", $json);
		}
	}

	private function ensure_branch ($dirname) {
		if (is_dir("$this->root/$dirname")) {
			return true;
		} else {
			return mkdir("$this->root/$dirname", 0777, true);
		}
	}
}