<?php

class Axite_driver_mysql extends Axite_Plugin
{
	public $allow_instances = true;
	public $insert_ids = array();
	public $stats = array('queries' => 0, 'cache' => 0, 'staticCache' => 0, 'time' => 0);

	private $link = false;
	private $staticCacheWrites = 0;

	public function onSystemInit() {
		// $this->mode = $this->config['mode'];
		// if ($this->mode != 'plain') throw new Exception('Unsupported mode.', 1220);

		if ($this->config['use_static_cache']) $this->loadStaticCache();
		if ($this->config['database']) $this->connect();
	}

	public function core_finish() {
		if ($this->config['use_static_cache'] && $this->staticCacheWrites) $this->saveStaticCache();
		if(DEBUG) debug::log("Total queries: {$this->stats['queries']}. Cache hits: {$this->stats['cache']}. Static cache hits: {$this->stats['staticCache']}. Time: {$this->stats['time']}");
	}

	public function clearStaticCache() {
		$this->loadStaticCache();
		unlink($this->staticCacheFile);
	}

	private function saveStaticCache() {
		$string = '$this->staticCache = ' . var_export($this->staticCache, true);
		file_put_contents($this->staticCacheFile, "<?php\n$string\n?>");
		if(DEBUG) debug::log("Static cache updated: $this->staticCacheWrites new items");
	}

	private function loadStaticCache() {
		$dir = $this->amwe->config['dir_site'] .'cache/' . $this->id . '/';
		$this->staticCacheFile = $dir . 'staticCache.php';
		if (!is_dir($dir)) mkdir($dir);
		if (is_file($this->staticCacheFile)) include $this->staticCacheFile;
	}


	/**
	 * Connect to database
	 * @return resource Link to database
	 */
	private function connect() {
		set_error_handler(array($this, 'connect_error_handler'));

		// Connect to mySQL
		$this->link = mysql_connect($this->config['host'], $this->config['username'], $this->config['password']);

		// Select database
		mysql_select_db($this->config['database'], $this->link);

		// Set charset
		if(!empty($this->config['charset'])) {
			mysql_set_charset($this->config['charset'], $this->link);
			mysql_query("SET names {$this->config['charset']}", $this->link);
		}

		// Set SQL options
		mysql_query("SET SESSION sql_mode='ALLOW_INVALID_DATES'", $this->link);

		restore_error_handler();

		return $this->link;
	}

	private function connect_error_handler($errno, $errstr) {
		throw new Exception("Connecting to '{$this->config['host']}' as '{$this->config['username']}' unsuccssfull: $errno $errstr" . mysql_error(), 3221);
	}

	/**
	 * Run query
	 * Use HERE for selection from current table
	 * Use ACTUAL for selection from actual view of current table (w/o inactive, private, expired, etc cards)
	 * @todo Use BRIEF for selection from brief view of current table (only brief columns)
	 *
	 * @param string $q SQL query
	 * @param string $parse_options 'col' — parse one column, 'silent' — silent mode (ignore errors), 'row' — one row query
	 * @param string $key Column name to use as a key for associative array
	 * @return array Result rows
	 */
	public function q($q, $parse_options = 0, $key = null) {
		static $cache;
		$time = getscriptmicrotime();

		// replacement for HERE / ACTUAL / BRIEF keywords
//		$q = preg_replace('/^(SELECT .* FROM|INSERT( INTO)?|INSERT|UPDATE|DELETE( FROM)?) ACTUAL/iU', '$1 '.$this->views['actual'], $q);
//		$q = preg_replace('/^(SELECT .* FROM|INSERT( INTO)?|INSERT|UPDATE|DELETE( FROM)?) HERE/iU', '$1 '.$this->table, $q);
//		$q = preg_replace('/^SELECT BRIEF/iU', 'SELECT '.$this->brief_fields, $q);

		// Explode parse options
		$parse_options = " $parse_options ";
		$parse_options_silent = strpos($parse_options, ' silent ') !== false;
		$parse_options_static = strpos($parse_options, ' static ') !== false;
		$parse_options_cache = strpos($parse_options, ' cache ') !== false || $parse_options_static;
		$parse_options_EAV = strpos($parse_options, ' EAV ') !== false;

		// Cache
		$cacheId = crc32($q . $parse_options);
		if ($parse_options_cache && isset($cache[$cacheId])) {
			$this->stats['cache']++;
			if(DEBUG) debug::log("(cache hit!)\n$q [$parse_options, $key]", 'query');
			return $cache[$cacheId];
		}

		// Static cache
		if ($parse_options_static && isset($this->staticCache[$cacheId])) {
			$this->stats['staticCache']++;
			if(DEBUG) debug::log("(static cache hit!)\n$q", 'query');
			return $this->staticCache[$cacheId];
		}

		// run query
		if(DEBUG) debug::log($q . " [$parse_options, $key]", 'query');
		$result = mysql_query($q, $this->link);

		// Apply statistics
		$this->stats['queries']++;

		// Parse result
		$return = false;
		if ($error = mysql_error()) {
			if(!$parse_options_silent) throw new Exception("$q\n$error", 2221);
		} elseif (is_resource($result)) {
			if ($parse_options_EAV) {
				$return = $this->q_parse_EAV($result, $parse_options);
			} else {
				$return = $this->q_parse_normal($result, $parse_options, $key);
			}

			if ($parse_options_cache) $cache[$cacheId] = $return;
			if ($parse_options_static) {
				$this->staticCache[$cacheId] = $return;
				$this->staticCacheWrites++;
			}
		}

		$this->stats['time'] += getscriptmicrotime() - $time;

		return $return;
	}

	private function q_parse_normal(&$result, $parse_options, $key) {
		// Choose a key column for assoc parsing
		if ($key === null && is_resource($result)) {
			$table = mysql_field_table($result, 0);
			if ($table) $key = reset($this->tableKeys($table));
			else $key = 'id';
		}

		$parse_options_row = strpos($parse_options, ' row ') !== false;
		$parse_options_col = strpos($parse_options, ' col ') !== false;
		$parse_options_impose = strpos($parse_options, ' impose ') !== false ;

		// Parse
		$items = array();
		$i = 0;
		while ($row = mysql_fetch_assoc($result)) {
			$i = isset($row[$key]) ? $row[$key] : ++$i; // determine the row key
			if (!$parse_options_impose && isset($items[$i])) continue; // do not impose later rows onto earlier ones with same key
			if (isset($row[$key]) && empty($row[$key])) $i = '__empty'; // replace $items[''] => $items['__empty']
			$items[$i] = $parse_options_col ? reset($row) : $row;
		}
		if (count($items)) {
			return $parse_options_row ? reset($items) : $items;
		}
	}

	private function q_parse_EAV(&$result, $parse_options) {
		$parse_options_multiple = strpos($parse_options, ' multiple ') !== false;

		$items = array();
		while ($row = mysql_fetch_assoc($result)) {
			if ($parse_options_multiple) {
				// entity => value_id => array( a => attribute, v => value)
				// (entity can have multiple attributes with same name)
				$items[$row['e_id']][$row['v_id']] = array('a'=>$row['a_id'],'v'=>$row['v']);
			} else {
				// entity => attribute => value
				// (each attrubute is unique per entity)
				$items[$row['e_id']][$row['a_id']] = $row['v'];
			}
		}
		return $items;
	}

	/**
	 * Query with separated table / id / field / options
	 * @param string|array|null $table Table(s) name
	 * @param string|array|null $ids Row ID for single row or array of ID for multiple
	 * @param string|array|null $fields Field name or array of names for multiple
	 * @param array $options Options
	 */
	public function qSep($tables = null, $id = null, $fields = null, $options = null) {
		if (is_array($tables)) {
			$table1 = reset($tables);
			array_remove($tables, '');
		} else {
			$table1 = $tables;
		}

		$tableKeys = $this->tableKeys($table1);

		// ids
		switch (gettype($id)) {
			case 'array':
			case 'integer':
			case 'string':
				if ($id == '__empty') $id='';
				$conditionQ = $this->buildKeysConditionQ($id, $tableKeys, $table1);
				break;
			case 'NULL':
				$conditionQ = '1';
				break;
			default:
				throw new Exception ('Wrong type of $ids', 2000);
				break;
		}

		// add primary key match option when querying more than one table
		if (is_array($tables)) {
			if ($tableKeys) {
				$tableKey1 = reset($tableKeys);
				foreach ($tables as $table) {
					if ($table != $table1)
						$conditionQ .= " AND `$table`.`$tableKey1` = `$table1`.`$tableKey1`";
				}
			}
			$tables = implode(',', $tables);
		}

		// add custom WHERE from $options
		if ($options['where']) {
			$conditionQ .= " AND " . $options['where'];
		}

		// fields
		if     (is_array($fields)) $fieldsQ = implode(',', $fields);
		elseif (empty($fields))    $fieldsQ = '*';
		else                       $fieldsQ = $fields;

		// ORDER and LIMIT
		$optionsQ = $this->buildOptionsQ($options);

		// build query
		$q = "SELECT $fieldsQ FROM $tables WHERE $conditionQ $optionsQ";

		// query mode and query
		$options = ifset($id, 'row ') . (is_scalar($fields)&&$fields ? 'col ' : '') . $options['cache'];
		$result = $this->q($q, $options);

		return $result;
	}

//	public function qParams($params) {
//		$table = $params['table'];
//		$id = $params['id'];
//		$field = $params['field'];
//		return $this->qSep($table, $id, $field, $params);
//	}

	public function createMapper($table = null, $id = null, $field = null, $options = null) {
		return new amwe_mapper__ds_mysql_default($this->amwe, array(
			'table' => $table,
			'ds' => $this,
			'id' => $id,
			'field' => $field,
			'order' =>  $options['order'],
			'limit' => $options['limit'],
		));
	}

	public function store($data, $options = null) {
		return $this->goThruData($data, $options, 'store');
	}

	public function insert($data, $options = null) {
		return $this->goThruData($data, $options, 'insert');
	}

	public function update($data, $options = null) {
		return $this->goThruData($data, $options, 'update');
	}

	public function delete($data, $options = null) {
		return $this->goThruData($data, $options, 'delete');
	}

	private function goThruData($data, $options, $method) {
//echo '<pre>'; print_r($data); echo '</pre>';
		if (isset($options['where']) && is_array($options['where']))
			$whereQ = implode(' AND ', $options['where']);
		else
			$whereQ = '';

		$result = array();
		if ($data) foreach ($data as $table => $rows) {
//			$table = $this->amwe->plugin_exists("view/$viewname") ? $this->amwe->point("view/$viewname")->table() : $viewname;
			$tableKeys = $this->tableKeys($table);
			$uniqueKeys = $this->tableUniqueKeys($table);
			$autoIncrementKey = $this->tableAutoIncrementKey($table);

			if ($rows) foreach ($rows as $id => $fields) {
				$idColumns = explode(':', $id);
				foreach ($uniqueKeys as $number=>$key) {
					// Set up fields for primary unique keys
					// $uniqueKeys begins from 1, $idColumns begins from 0
					if (!isset($fields[$key])) $fields[$key] = $idColumns[$number-1];
				}
//				if (isset($options['idField'])) $fields[$options['idField']] = $id;
//				if ($id == '__empty') $id = '';

				if ($method == 'insert' || $method == 'store') {
					// unique key exists?
					$uniqueKeyExists = $this->tableUniqueKeyExists($table, $fields);

					// unset existing unique keys
					if ($method == 'insert' && $uniqueKeyExists) {
						foreach ($uniqueKeys as $ukey) {
							if (isset($fields[$ukey])) {
								unset($fields[$ukey]);
							}
						}
					}
				}

				$set = $this->buildSetQ($fields, $table);

				if ($method == 'insert' || $method == 'store' && !$uniqueKeyExists) {
					$q = "INSERT $table $set";
					$this->q($q);
//echo $q;
					// auto increment key value
					$insert_id = mysql_insert_id();
					array_push($this->insert_ids, $insert_id);
					// return values for unique keys
					foreach ($uniqueKeys as $ukey) {
						if(!isset($fields[$ukey])) $fields[$ukey] = '';
						$result[$table][$insert_id][$ukey] = $fields[$ukey];
					}
					// return values for auto increment keys
					if ($autoIncrementKey) $result[$table][$insert_id][$autoIncrementKey] = $insert_id;
				}

				if ($method == 'update' || $method == 'store' && $uniqueKeyExists) {
					$where = $this->buildKeysConditionQ($fields, $tableKeys);
					if ($whereQ) $where .= " AND $whereQ";
					$optionsQ = $this->buildOptionsQ($options);

					$q = "UPDATE $table $set WHERE $where $optionsQ";
					$this->q($q);
//echo $q;
					foreach ($uniqueKeys as $ukey) $result[$table][$id][$ukey] = $fields[$ukey];
					// return values for auto increment keys
					if ($autoIncrementKey) $result[$table][$id][$autoIncrementKey] = $fields[$autoIncrementKey];
				}

				if ($method == 'delete' || !empty($fields['__delete'])) {
					$where = $this->buildKeysConditionQ($fields, $tableKeys);
					if ($whereQ) $where .= " AND $whereQ";
					$optionsQ = $this->buildOptionsQ($options);

					$q = "DELETE FROM $table WHERE $where $optionsQ";
					$this->q($q);
				}

			}
		}
		return $result;
	}

	public function lastInsertId() {
		return end($this->insert_ids);
	}

	public function select($id, $options = null) {
		throw new Exception('Not implemented', 1220);
//		$conditionQ = $this->buildConditionQ($id);
//		$optionsQ = $this->buildOptionsQ($options);
//		$fields = isset($options['fields']) ? implode(',', $options['fields']) : '*';
//		$result = $this->q("SELECT $fields FROM HERE $conditionQ $optionsQ");
//		return $result;
	}

	public function meta($table, $columns = null) {
		if ($columns && $columns!='*') {
			if(is_string($columns)) $columns = explode(',', $columns);
			$columns = array_map('trim', $columns);
			$columns = implode("','", $columns);
			$where = "WHERE Field IN ('$columns')";
		} else {
			$where = "";
		}
		$result['fields'] = $this->q("SHOW FULL COLUMNS FROM $table $where", 'static', 'Field');
		return $result;
	}

	public function tableKeys($table) {
		static $cache = array();
		if (isset($cache[$table])) return $cache[$table];

		$result = $this->tableUniqueKeys($table);
		if (!$result) {
			$result = $this->q("SHOW COLUMNS FROM $table", 'static row', 'Field');
			$result = array($result['Field']);
		}

		return $cache[$table] = $result;
	}

	public function tableAutoIncrementKey($table) {
		static $cache = array();
		if (isset($cache[$table])) return $cache[$table];

		$result = $this->q("SHOW COLUMNS FROM $table WHERE Extra = 'auto_increment'", 'static row col', 'Field');

		return $cache[$table] = $result;
	}

	public function tableUniqueKeyExists($table, $fields) {
		$uniqueKeys = $this->tableUniqueKeys($table);
		if ($uniqueKeys) {
			// query for unique keys
			$whereQ = array();
			foreach ($uniqueKeys as $ukey) {
				if(!isset($fields[$ukey])) $fields[$ukey] = '';
				$whereQ[] = " `$ukey` = '{$fields[$ukey]}'";
			}
			$whereQ = implode(" AND ", $whereQ);
			$fieldsQ = implode("`,`", $uniqueKeys);
			return $this->q("SELECT `$fieldsQ` FROM `$table` WHERE $whereQ");
		} else {
			return false;
		}
	}

	public function tableUniqueKeys($table) {
		static $cache = array();
		if (isset($cache[$table])) return $cache[$table];

		$result = $this->q("SHOW INDEX FROM $table", 'static', false);
		$keys = array();
		if($result) {
			foreach ($result as $row) {
				if (!$row['Non_unique']) {
					$keys[$row['Seq_in_index']] = $row['Column_name'];
				}
			}
		}
		return $cache[$table] = $keys;
	}

	function implodeQ($fields, $implode = 'AND') {
		if (is_array($fields)) {
			foreach ($fields as $key=>$value) {
				$condition[] = "`$key` = '$value'";
			}

			if (empty($condition))
				return $fields ? 1 : 0;
			else
				return ' (' . implode(" $implode ", $condition) . ') ';
		} else {
			return $fields ? 1 : 0;
		}
	}

	/**
	 * Parse condition into WHERE query part
	 * @param string|array $fields Fields or array with (key=>field)'s if $keys is omitted
	 * @param array $keys Keys
	 * @return string "`key` = 'value' AND `key2` = 'value2'"
	 */
	function buildKeysConditionQ($fields, $keys, $table = null) {
		$condition = array();

		if ($keys)
			foreach ($keys as $number=>$keyName) {
				if (is_array($fields)) {
					$id = addslashes($fields[$keyName]);
					$condition[] = ($table ? "`$table`." : '') . "`$keyName` = '$id'";
				} else {
					$id = addslashes($fields);
					$condition[] = ($table ? "`$table`." : '') .  "`$keyName` = '$id'";
					break;
				}
			}
		elseif (is_array($fields))
			foreach ($fields as $key=>$value) {
				$condition[] = "`$key` = '$value'";
			}

		return empty($condition) ? 1 :implode(' AND ', $condition);
	}

	function buildValuesConditionQ($values, $fieldName) {
		if ($values)
			return "`$fieldName` IN ('" . implode("', '", $values) . "')";
	}

	/**
	 * Parse options into query
	 * Available options: limit, order
	 */
	function buildOptionsQ($options) {
		$subq = "";
		if (isset($options['order'])) $subq .= " ORDER BY {$options['order']}";
		if (isset($options['limit'])) $subq .= " LIMIT {$options['limit']}";
		return $subq;
	}

	/**
	 * Parse associative array with fieldname=>value pairs into SET query part
	 * @param array $data Associative array with fieldname=>value pairs
	 * @param string $table
	 * @param string $parse_options 't' — trim values
	 * @return string "SET fieldname='$value', fieldname='$value' ..."
	 */
	function buildSetQ($data, $table, $parse_options = '') {
		$macro = array('NOW()');
		$str = array();

		foreach($data as $key=>$value) {
			// Check if a column is allowed to write
			$meta = $this->meta($table);
			$allowed_columns = $meta['fields'];
			if (!isset($allowed_columns[$key])) continue;

			if ($value === null) {
				// NULL
				$value = 'NULL';
			} elseif (!in_array($value, $macro)) {
				// Make a query part for one field
				if (strpos($parse_options, 'trim') !== false) $value = trim($value);
				if (is_array($value)) $value = implode(',', $value);
				$value = "'".$this->escape($value)."'";
			}

			$str[] = "`$key` = $value";
		}
		if(!count($str)) return false; // empty set on result
		$set = implode(',', $str);

		return 'SET ' . $set;
	}

	/**
	 * Escape string to make queries safe
	 * @wrapper
	 */
	public function escape($string) {
		if(is_array($string))
			return array_map('mysql_real_escape_string', $string);
		else
			return mysql_real_escape_string($string);
	}

	/**
	 * Return error for last query
	 * @wrapper
	 */
	function lastError() {
		return mysql_error();
	}

	/**
	 * Return number of affected rows
	 * @wrapper
	 */
	function affectedRows() {
		return mysql_affected_rows();
	}
}