<?php

class Axite_driver_thetest extends Axite_DS_Plugin
{
	function ds_get($entity, $attribute) {
		echo "test get";
	}

	function ds_set($entity, $attribute, $value) {
		echo "test set";
	}

	function ds_fulllist($entity) {
		echo "test fulllist";
	}

	function ds_delete($entity, $attribute = null) {
		echo "test delete";
	}
}