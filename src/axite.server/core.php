<?php
include "functions.php";




/** Axite constants */
class AXITE {
	const VERSION = "0.2";
	const UNSET_VALUE = "__AXITE_UNSET";
	static $SYSTEM_FIELDS = array('_has_childs', '_entity_key', '_origin_ds', '_is_real');
}





class Axite_Core {
	public $plugins = Array();
	public $log = Array();

	function run () {
		// Load config
		$this->config = parse_ini_file("config.ini", true);

		// Turn debug mode on
		define('DEBUG', (bool)$this->config['core']['debug']);
		if (DEBUG) include 'debug.php';
		if (DEBUG) debug::log("Start");

		// Init & Run
		$this->ds_dispatcher = new Axite_DsDispatcher($this);
		$this->init_plugins();
		$this->insert('onSystemInit');
		$this->insert('urlRouter');
		$this->insert('onSystemFinish');
	}

	/** Plugin INSERT point */
	function insert ($insertName /* ...any params... */) {
		$args = func_get_args(); array_shift($args);
		$result = null;

		foreach ($this->plugins as $pluginKey => $plugin) {
			if (is_callable(array($plugin, $insertName))) {
				if(DEBUG) debug::log("$insertName: " . get_class($plugin) . " launched", "insert");

				// call external module method with all params passed
				// adding previous iteration result as last param
				$result = call_user_func_array(array($plugin, $insertName), $args + array($result));
			}
		}

		return $result;
	}

	private function init_plugins () {
		// Include files
		foreach ($this->config['plugins'] as $name => $parentClassName) {
			if ($parentClassName == 1) {
				include $name.".php";
			}
		}

		// Init plugins
		foreach ($this->config['plugins'] as $name => $parentClassName) {
			if (!empty($parentClassName)) {
				$className = $this->get_plugin_classname(!is_numeric($parentClassName) ? $parentClassName : $name);
				if (!in_array('Axite_Multiinstancable_PluginInterface', class_implements($className)) || !is_numeric($parentClassName)) {
					$this->plugins[$name] = new $className($this, $this, $this->config[$name]);
					$this->plugins[$name]->plugin_name = $name;
				}
			}
		}
	}

	private function get_plugin_classname ($filename) {
		return "Axite_" . str_replace(".", "_", basename($filename, ".php"));
	}
}





class Axite_DsDispatcher {
	private $rootMode = false;

	function __construct ($core) {
		$this->core = $core;
	}

	private $lastErrorMessage = false;
	function getLastErrorMessage () {
		return $this->lastErrorMessage;
	}

	function asRoot () {
		$this->rootMode = true;
		$rootThis = clone $this;
		$this->rootMode = false;
		return $rootThis;
	}

	function set       ($key, $value = null) {return $this->execute('ds_set',       $key, $value);}
	function keys      ($key)                {return $this->execute('ds_keys',      $key);}
	function brieflist ($key)                {return $this->execute('ds_brieflist', $key);}
	function fulllist  ($key)                {return $this->execute('ds_fulllist',  $key);}
	function delete    ($key)                {return $this->execute('ds_delete',    $key);}

	function setData ($data) {
		$result = array();
		foreach ($data as $key => $value) {
			$result[$key] = $this->execute('ds_set', $key, $value);
		}
		return $result;
	}

	function get ($kv) {
		if (is_scalar($kv)) {
			$scalar = true;
			$kv = Array($kv);
		}

		foreach ($kv as $key) {
			$result[$key] = $this->execute('ds_get', $key);
		}

		return $scalar !== true ? $result : reset($result);
	}

	function move ($src, $dst) {
		$data = $this->execute('ds_get', $src);

		foreach ($data as $attribute => $value) {
			$dataNew["$dst:$attribute"] = $value;
		}

		$setResult = $this->setData($dataNew);
		$this->delete($src);

		return ($setResult) ? $dst : false;
	}

	private function explode_dea ($key) {
		$key = ltrim($key, '/');
		// Check if request is for peculiar mapper (my_mapper/path/entity)
		foreach ($this->list_mappers() as $mapperName) {
			if (strncmp($key, $mapperName, strlen($mapperName)) === 0) {
				$ds = $mapperName;
				// $ea = substr($key, strlen($mapperName));
				$ea = $key;
				list($entity, $attribute) = explode_set(":", $ea, 2);
				return array($ds, $entity, $attribute);
			}
		}

		// Check if request is for peculiar ds (my_peculiar_ds/path/entity)
		foreach ($this->list_ds() as $dsName) {
			if (strncmp($key, $dsName, strlen($dsName)) === 0) {
				$ds = $dsName;
				$ea = substr($key, strlen($dsName));
				list($entity, $attribute) = explode_set(":", $ea, 2);
				return array($ds, $entity, $attribute);
			}
		}

		// Use general mapper as root ((assumed general_mapper)/path/entity)
		if ($this->core->config['core']['general_mapper']) {
			list($entity, $attribute) = explode_set(":", $key, 2);
			return array($this->core->config['core']['general_mapper'], $entity, $attribute);
		}

		// Use general ds as root ((assumed general_ds)/path/entity)
		if ($this->core->config['core']['general_ds']) {
			list($entity, $attribute) = explode_set(":", $key, 2);
			return array($this->core->config['core']['general_ds'], $entity, $attribute);
		}

		return array(false, false, false);
	}

	private function execute ($method, $key, $value = null) {
		list($ds, $entity, $attribute) = $this->explode_dea($key);

		try {
			if (isset($this->mappers[$ds])) {
				// mapper
				list($entity, $attribute) = $this->mappers[$ds]->rewritePath($entity, $attribute);
				if (!$this->rootMode && $this->mappers[$ds]->access($method, $entity, $attribute, $value) !== true) throw new Exception('Access denied');
				return $this->mappers[$ds]->$method($entity, $attribute, $value);
			} elseif (isset($this->core->plugins["ds.$ds"])) {
				// ds
				if (!$this->rootMode && $this->core->plugins["ds.$ds"]->access($method, $entity, $attribute, $value) !== true) throw new Exception('Access denied');
				return $this->core->plugins["ds.$ds"]->$method($entity, $attribute, $value);
			} else {
				return false;
			}
		} catch (Exception $e) {
			$this->lastErrorMessage = $e->getMessage();
			return false;
		}
	}

	private function list_ds () {
		$result = array();
		foreach ($this->core->plugins as $name => $plugin) {
			if ($plugin instanceof Axite_DS_PluginInterface) {
				$result[] = str_replace('ds.', '', $name);
			}
		}
		return $result;
	}

	private function list_mappers () {
		if (empty($this->mappers)) {
			foreach(get_declared_classes() as $className) {
				if (substr($className, 0, 13) == 'Axite_mapper_') {
					$mapperName = substr($className, 13);
					$this->mappers[$mapperName] = new $className($this->core);
				}
			}
		}

		return array_keys($this->mappers);
	}
}










interface Axite_DS_PluginInterface {
	function ds_get      ($entity, $attribute        );
	function ds_set      ($entity, $attribute, $value);
	function ds_fulllist ($entity                    );
	function ds_delete   ($entity, $attribute = null );
}



interface Axite_Multiinstancable_PluginInterface {

}



abstract class Axite_Plugin {
	public $plugin_name = null;

	function __construct ($core, $parent, &$config = array()) {
		$this->core = $core;
		$this->parent = $parent;
		$this->config = $config;
	}
}



abstract class Axite_DS_Plugin extends Axite_Plugin implements Axite_DS_PluginInterface, Axite_Multiinstancable_PluginInterface {
	public $plugin_name = null;

	function __construct ($core, $parent, &$config = array()) {
		$this->core = $core;
		$this->parent = $parent;
		$this->config = $config;
	}

	function access ($method) {
		return true;
	}

	function get_ds_name () {
		return str_replace('ds.', '', $this->plugin_name);
	}

}



abstract class Axite_mapper {
	public $dsName;
	public $ds;
	public $mapperRoot;

	protected $allowedMethods = array('ds_get', 'ds_keys');

	function __construct ($core) {
		$this->core = $core;
		$this->ds_dispatcher = $core->ds_dispatcher;
		if ($this->dsName) $this->ds = $core->plugins[$this->dsName];
	}

	function __call($method, $params) {
		if (method_exists($this->ds, $method))
			return call_user_func_array(array($this->ds, $method), $params);
		else
			throw new Exception("$method not exists in ds.$this->dsName");
	}

	function access ($method, $entity, $attribute, $value) {
		if (empty($this->is_admin) && !in_array($method, $this->allowedMethods)) {
			return 'Access denied!';
		} else {
			return true;
		}
	}

	function rewritePath($entity, $attribute) {
		return array(
			str_replace($this->mapperRoot, '', $entity),
			$attribute
		);
	}
}