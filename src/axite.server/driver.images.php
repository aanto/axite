<?php

class Axite_driver_images extends Axite_DS_Plugin 
{
	private $extensions = array(".jpg", ".png");

	function onSystemInit() {
		$this->virtualRoot = $this->config['rootdir'] or $this->virtualRoot = $this->get_ds_name();
		$this->root = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->virtualRoot;
	}		

	function ds_keys ($entity) {
		$entity = trim($entity, '/');
		static $cache = array();
		if (isset($cache[$entity])) {
			if (DEBUG) debug::log("ds_filestore_images [cache hit] keys $entity", "ds_get");
			return $cache[$entity];
		}

		$dirname = "$this->root/$entity/";
		if (!is_dir($dirname)) return array();
		
		$items = array();
		$sortKey = null;

		$filelist = scandir($dirname);
		$filelist = array_diff($filelist, array(".", ".."));

		foreach ($filelist as $file) {
			if (is_file("$dirname/$file")) {
				$item = "/$this->virtualRoot/$entity/$file";
				$items[] = $item;
			}
		}

		if (DEBUG) debug::log("ds_filestore_images fulllist $entity", "ds_get");
	
		$cache[$entity] = $items;

		return $items;
	}

	function ds_get ($entity, $attribute) {
		foreach ($this->extensions as $ext) {
			$filename = "$this->root/$entity$ext";
			if (is_file($filename)) {
				$stat = stat($filename);
				return array('size' => $stat['size']);
			}
		}
		return false;
	}

	function ds_set ($entity, $attribute, $value) {
		// Must save base64 encoded file
		list($meta, $data_base64) = explode(',', $value);
		list($type, $ext, $enc) = preg_split("/[:\/;]/", substr($meta,5));
		$data = base64_decode($data_base64);

		$this->ensure_branch(dirname($entity));
		// remove .jpeg from entity name images/my/file.jpeg
		if (substr($entity, -strlen($ext)-1) == ".$ext") $entity = substr($entity, 0, -strlen($ext)-1);
		file_put_contents("$this->root/$entity.$ext", $data);

		if (DEBUG) debug::log("ds_filestore_images save: $entity", "ds_set");

		// Return image virtual path
		return "/$this->virtualRoot/$entity.$ext";
	}

	function ds_delete ($entity, $attribute = null) {
		return unlink("$this->root/$entity");
/*
		if ($attribute) {
			$this->saveCache[$entity][$attribute] = AXITE::UNSET_VALUE;
		} else {
			$this->saveCache[$entity] = AXITE::UNSET_VALUE;
		}

		if (DEBUG) debug::log("ds_filestore delete: $entity:$attribute", "ds");
*/
	}

	function ds_fulllist ($entity) {
		throw new Exception("Not implemented");
/*
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
*/
	}


	private function ensure_branch ($dirname) {
		if (is_dir("$this->root/$dirname")) {
			return true;
		} else {
			return mkdir("$this->root/$dirname", 0777, true);	
		}
	}
}