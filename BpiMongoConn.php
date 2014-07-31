<?php

	class BpiMongoConn {
		
		private static $DB_NAME = "TimeTracker";
		private static $MONGO_SERVER = "mongodb://54.88.133.189/api/";
		private static $TRANS_SERVER = "http://54.88.133.189/api/transaction.cgi?";
		private static $COLL_NAME = "time";
		private $COLLECTION;
		private $TIME_GENERATOR;
		private $TIME_LOG;
		private $DRINK_LOG;
		private $account;
		private $user;

		public function __construct($user) {
			$m = new MongoClient(BpiMongoConn::$MONGO_SERVER);
			$db = $m->selectDB(BpiMongoConn::$DB_NAME);
			$this->COLLECTION = $db->selectCollection(BpiMongoConn::$COLL_NAME);
			$this->user = $user;
			$query = array('user' => $user);
			if (NULL == $this->COLLECTION->findOne($query)) {
				echo "Adding user";
				if(!$this->add_user()) {
					echo "\nError communicating with server to add user";
					return;
				}
			}
			$this->account = $this->COLLECTION->findOne($query);
		}

		public static function initialize_time_tracker() {
			$m = new MongoClient(BpiMongoConn::$MONGO_SERVER);
			$db = $m->selectDB(BpiMongoConn::$DB_NAME);
			$coll = $db->selectCollection(BpiMongoConn::$COLL_NAME);

			$tg_id = BpiMongoConn::add_trans_server_account("TIME_GENERATOR");
			$tg_subacct_id = BpiMongoConn::add_trans_server_subaccount($tg_id);
			$tg = array('user' => "TIME_GENERATOR", 'account' => $tg_id, 'time_subaccount' => $tg_subacct_id);
			$coll->insert($tg);

			$tl_id = BpiMongoConn::add_trans_server_account("TIME_LOG");
			$tl_subacct_id = BpiMongoConn::add_trans_server_subaccount($tl_id);
			$tl = array('user' => "TIME_LOG", 'account' => $tl_id, 'time_subaccount' => $tl_subacct_id);
			$coll->insert($tl);

			$dl_id = BpiMongoConn::add_trans_server_account("DRINK_LOG");
			$dl_subacct_id = BpiMongoConn::add_trans_server_subaccount($dl_id);
			$dl = array('user' => "DRINK_LOG", 'account' => $dl_id, 'time_subaccount' => $dl_subacct_id);
			$coll->insert($dl);
		}

		public function drink($amt) {
			$balance = $this->account['time_balance'];
			if($balance >= $amt) {
				echo $this->split_docs($this->account, $balance, $amt). " ";
			} else {
				echo "Insufficient funds";
			}
		}

		public function adjust_time($amt) {
			if($amt >= 0) {
				$this->add_time($amt);
			} else {
				$this->subtract_time($amt);
			}	
		}

		private function add_time($amt) {
			$record = array('amount' => $amt);
			$this->COLLECTION->insert($record);
			$this->TIME_GENERATOR = $this->COLLECTION->findOne(array('user' => 'TIME_GENERATOR'));
			echo $record['_id'];
			file_get_contents(BpiMongoConn::$TRANS_SERVER . "method=addItemToSubAccount&subId0=" . 
				$this->TIME_GENERATOR['time_subaccount'] . "&itemId=" . $record['_id'] );
			$this->add_time_transaction($record['_id']);
			$this->update_balance($amt);
		}

		private function add_time_transaction($record_id) {
			$params = array(
	            'id0' => $this->account['account'],
        		'id1' => $this->TIME_GENERATOR['account'],
        		'subId0' => $this->account['time_subaccount'],
       			'subId1' => $this->TIME_GENERATOR['time_subaccount'],
       			'itemsId0' => "{\"items\":[\"empty\"]}",
       			'itemsId1' => "{\"items\":[\"empty\",\""  . $record_id . "\"]}"
	        );
	        $options = array(
	            'http' => array(
	            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
	            'method' => 'GET',
	            'content' => http_build_query($params),
	           	),
	        );
	        $context = stream_context_create($options);
	        $result = file_get_contents(BpiMongoConn::$TRANS_SERVER, false, $context);
	        if($result->{"status"} != "success") {
				echo "Server error recording transaction.";
				return;
			}
		}

		private function add_user() {
			$acct_id = $this->add_trans_server_account($this->user);
			$subacct_id = $this->add_trans_server_subaccount($acct_id);

			$new_account = array('user' => $this->user, 'time_balance' => 0, 
				'account' => $acct_id, 'time_subaccount' => $subacct_id);
			$this->COLLECTION->insert($new_account);
			return true;
		}

		private static function add_trans_server_account($name) {
			$acct_string = file_get_contents(BpiMongoConn::$TRANS_SERVER . "method=addAccount&username=" . $name);
			$acct = json_decode($acct_string);
			if($acct->{"status"} != "success") {
				echo "Server error adding new user account";
				return;
			}
			return $acct->{"content"}->{"_id"};
		}

		private static function add_trans_server_subaccount($acct_id) {
			$subacct_string = file_get_contents(BpiMongoConn::$TRANS_SERVER . "method=addSubAccount&id0=" . $acct_id);	
			$subacct = json_decode($subacct_string);
			if($subacct->{"status"} != "success") {
				echo "Server error adding new user subaccount.";
				return;
			}
			return $subacct->{"content"}->{"_id"};
		}

		private function split_docs($acct, $balance, $amt, $type) {
			$acct_update = $this->account;		
			$acct['balance'] = $balance - $amt;
			$this->record_adjustment($acct['user'], $amt, $type);
			$this->COLLECTION->update($acct, $acct_update);
		}

		private function update_balance($amt) {
			$this->account['balance'] += $amt; 
			$this->COLLECTION->update(array('user' => $user), $this->account);
		}

		public function record_adjustment($user, $amt, $type) {
			$id = user_name($user) . "_" . $amt;
			$record = array('_id' => $id, 'amount' => $amt, 'type' => $type);
			$this->COLLECTION->insert($record);
			return $record['_id'];
		}

		public static function make_id($user, $type, $amt) {
			$arr = explode('@', $user);
			$name = $arr[0];
			$time = new MongoDate();
			return $amt . "_" . $type . "_" . $user . "_" . $time;
		}

	}
?>