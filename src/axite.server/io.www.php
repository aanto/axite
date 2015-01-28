<?php

class Axite_io_www extends Axite_Plugin
{
	public $is_admin = false;

	function onSystemInit () {
		$this->ds_dispatcher = $this->core->ds_dispatcher;
		$this->tpl_root = $_SERVER['DOCUMENT_ROOT'] . $this->config['dir_templates'];
	}

	function urlRouter () {
		// parse url
		$url_parsed = parse_url(rawurldecode($_SERVER['REQUEST_URI']));
		$url_splitted = explode("/", ltrim($url_parsed['path'], "/"));

		$this->initSession();

		if ($this->user_account['access'] == 'admin') $this->is_admin = true;

		$this->public_url_splitted = $url_splitted;

		if ($this->core->insert('io_www_handle_url', $url_splitted)) {
			// url handleed by plugins
			null;
		} else if (isset($url_splitted[0]) && substr($url_splitted[0], 0, 1) == '-') {
			// ajax data requested
			$this->outputAjax($url_splitted);
		} else {
			$this->outputHtml($url_splitted);
		}
	}

	function getAccess () {
		return isset($_SESSION['axite']['account']['access']) ? $_SESSION['axite']['account']['access'] : 'web';
	}

	function getPage () {
		if (DEBUG) debug::log("io_www->getPage $this->qPage");
		return $this->ds_dispatcher->get($this->qPage);
	}

	private function initSession () {
		session_start();

		if (isset($_POST['login'])) {
			if ($this->core->config['admin']['login'] == $_POST['login'] && $this->core->config['admin']['password'] == $_POST['password'])
				$_SESSION['axite']['account'] = array(
					'name' => $_POST['login'],
					'access' => 'admin'
				);
		}

		if (isset($_POST['logout'])) {
			$_SESSION['axite']['account'] = null;
		}

		$this->user_account = isset($_SESSION['axite']['account']) ? $_SESSION['axite']['account'] : array('access' => 'web');
	}

	private function outputHtml ($url_splitted) {
		// prepare variables
		$ds = !empty($this->admin_ds) ? $this->admin_ds : "pages";
		$page_url = rtrim(implode("/", empty($url_splitted[0]) ? array('index') : $url_splitted), '/');
		$this->qPage = $ds . "/" . $page_url;
		$page = $this->page = $this->getPage();

		// http headers
		header("Content-Type: text/html; charset=utf-8");

		if (empty($page['_is_real']) || empty($page['public']) && !$this->is_admin) {
			include $this->tpl_root.'http.404.tpl';
		} else {
			include $this->tpl_root.'base.tpl';
		}
	}

	private function outputAjax ($url_splitted) {
		// http headers
		header("Content-Type: application/json; charset=utf-8");

		$command = array_shift($url_splitted);
		$qData = implode("/", $url_splitted);
		$data = array();

		switch ($command) {
			case '--mrq':
				// sleep(2);
				if (isset($_POST['messages'])) foreach (json_decode($_POST['messages'], true) as $message) {
					$data[] = $this->execMrqCommand($message);
				}
				break;
			case '--test':
				$data[] = $this->execMrqCommand(array('event' => 'getCollectionKeys', 'key' => $qData));
				// $data[] = $this->execMrqCommand($message);
				// var_dump($data);
				break;
			default:
				$data[] = array('event' => 'error', 'message' => "unknown AJAX command");
				break;
		}

		if (isset($data)) echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
	}

	private function execMrqCommand ($message) {
		switch ($message['event']) {
			case 'get':
				return array('event' => 'set', 'key' => $message['key'], 'data' => $this->ds_dispatcher->get($message['key']));
			case 'getCollectionKeys':
				return array('event' => 'setCollectionKeys', 'key' => $message['key'], 'data' => $this->ds_dispatcher->keys($message['key']));
			case 'set':
				$result = $this->ds_dispatcher->set($message['key'], $message['value']);
				if ($result) {
					return array('event' => 'setSuccess', 'key' => $message['key']);
				} else {
					return array('event' => 'setFail', 'key' => $message['key'], 'message' => $this->ds_dispatcher->getLastErrorMessage());
				}
			case 'move':
				$result = $this->ds_dispatcher->move($message['key'], $message['dst']);
				if ($result) {
					return array('event' => 'moveSuccess', 'key' => $message['key'], 'dst' => $message['dst']);
				} else {
					return array('event' => 'moveFail', 'key' => $message['key'], 'message' => $this->ds_dispatcher->getLastErrorMessage());
				}
				break;
			case 'delete':
				$result = $this->ds_dispatcher->delete($message['key']);
				if ($result) {
					return array('event' => 'deleteSuccess', 'key' => $message['key']);
				} else {
					return array('event' => 'deleteFail', 'key' => $message['key'], 'message' => $this->ds_dispatcher->getLastErrorMessage());
				}
			default:
				return array('event' => 'warning', 'text' => 'Unknown MRQ event ' . $message['event']);
		}
	}
}