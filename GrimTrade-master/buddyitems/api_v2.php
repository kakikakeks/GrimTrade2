<?php

	require_once 'db_v2.php';

	$api = new API($_REQUEST['request'], $_SERVER['HTTP_ORIGIN']);
//	var_dump($api);
	echo $api->processApi();

	class API {
		private $_db;
		/**
		 * Property: method
		 * The HTTP method this request was made in, either GET, POST, PUT or DELETE
		 */
		protected $method = '';
		/**
		 * Property: endpoint
		 * The Model requested in the URI. eg: /files
		 */
		protected $endpoint = '';
		/**
		 * Property: verb
		 * An optional additional descriptor about the endpoint, used for things that can
		 * not be handled by the basic methods. eg: /files/process
		 */
		protected $verb = '';
		/**
		 * Property: args
		 * Any additional URI components after the endpoint and verb have been removed, in our
		 * case, an integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1>
		 * or /<endpoint>/<arg0>
		 */
		protected $args = Array();
		/**
		 * Property: file
		 * Stores the input of the PUT request
		 */
		protected $file = Null;

		/**
		 * Constructor: __construct
		 * Allow for CORS, assemble and pre-process the data
		 */
		public function __construct($request) {
			$this->_db = new db();
			header("Access-Control-Allow-Orgin: *");
			header("Access-Control-Allow-Methods: *");
			header("Content-Type: application/json");

			$this->args = explode('/', rtrim($request, '/'));
			$this->endpoint = array_shift($this->args);
			if (array_key_exists(0, $this->args) && !is_numeric($this->args[0])) {
				$this->verb = array_shift($this->args);
			}

			$this->method = $_SERVER['REQUEST_METHOD'];
			if ($this->method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
				if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
					$this->method = 'DELETE';
				} else if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT') {
					$this->method = 'PUT';
				} else {
					throw new Exception("Unexpected Header");
				}
			}

			switch($this->method) {
				case 'DELETE':
				case 'POST':
					$this->request = $this->_cleanInputs($_POST);
					break;
				case 'GET':
					$this->request = $this->_cleanInputs($_GET);
					break;
				case 'PUT':
//					$this->request = $this->_cleanInputs($_GET);
//					$this->file = file_get_contents("php://input");
//					break;
				default:
					$this->_response('Invalid Method', 405);
					break;
			}
		}

		private function _cleanInputs($data) {
			$clean_input = Array();
			if (is_array($data)) {
				foreach ($data as $k => $v) {
					$clean_input[$k] = $this->_cleanInputs($v);
				}
			} else {
				$clean_input = trim(strip_tags($data));
			}
			return $clean_input;
		}


		private function _response($data, $status = 200) {
			header("HTTP/1.1 " . $status . " " . $this->_requestStatus($status));
			return json_encode($data);
		}

		private function _requestStatus($code) {
			$status = array(
				200 => 'OK',
				404 => 'Not Found',
				405 => 'Method Not Allowed',
				500 => 'Internal Server Error',
			);
			return ($status[$code])?$status[$code]:$status[500];
		}

		private function compress($data) {
			return base64_encode(gzencode($data));
		}
		
		private function getuser($args) {
			$uid = (int)$args[0];
			if ($data = $this->_db->getBuddy($uid)) {
				print_r($this->compress(json_encode($data)));
			}
			else {
				print_r($this->compress(json_encode(array('status' => 'failure'))));
			}
			
			exit();
		}


		private function createuser($args) {
			if (array_key_exists('uuid', $this->request)) {
				return array('status' => 'ok', 'uid' => $this->_db->createUser($this->request['uuid']));
			}
			else {
				return array('status' => 'failure');
			}
		}
		
		private function test_decode($args) {
			return gzdecode(base64_decode($this->request['json']));
		}
		
		private function test_encode($args) {
			return $this->compress('the encoding apparantly worked');
		}
		
		private function test_encode_with_data($args) {
			echo $this->compress($this->request['data']);
			exit();
		}

		private function update($args) {
			$json = gzdecode(base64_decode($this->request['json']));
			
			if ($this->_db->updateItems($json)) {
				return array('status' => 'ok');
			}
			else {
				// return array('status' => 'failure');
				$this->_response('{"status": "Access denied"}', 401);
				exit();
			}
		}


		public function processAPI() {
			if (method_exists($this, $this->endpoint)) {
				return $this->_response($this->{$this->endpoint}($this->args));
			}
			return $this->_response("No Endpoint: $this->endpoint", 404);
		}
	}