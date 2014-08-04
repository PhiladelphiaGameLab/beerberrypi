<?php

	/*
	 * Defines the class for manipulating the transaction server to log
	 * time for users, and spend their time balance on items.
	 */ 
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

		/* 
		 * Constructor connects to MongoDB server and adds user to the user collection
		 * and transaction server if existing not found 
		 */
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

		/* 
		 * Adds the three global users to the user collection and transaction server:
		 * 1) TIME_GENERATOR: Creates time documents to send to and increment user accounts
		 * 2) TIME_LOG: Stores time documents already added to user accounts
		 * 3) DRINK_LOG: Stores time documents sent from user account to drink 
		 */
		public static function initialize_time_tracker() {
			$m = new MongoClient(BpiMongoConn::$MONGO_SERVER);
			$db = $m->selectDB(BpiMongoConn::$DB_NAME);
			$coll = $db->selectCollection(BpiMongoConn::$COLL_NAME);
			BpiMongoConn::add_global_account("TIME_GENERATOR", $coll);
			BpiMongoConn::add_global_account("TIME_LOG", $coll);
			BpiMongoConn::add_global_account("DRINK_LOG", $coll);
		}

		/*
		 * Decrements the user's time balance, creates a document for the amount drank,
		 * and sends it from the user account to the DRINK_LOG.
		 */
		public function drink($amt) {
			$record_id = $this->split_time(-$amt);
			$this->DRINK_LOG = $this->COLLECTION->findOne(array('user' => 'DRINK_LOG'));
			$this->log_time_transaction($record_id, $this->DRINK_LOG);
		}

		/*
		 * Adjust the user's time balance and sends the change to the TIME_LOG.
		 */
		public function adjust_time($amt) {
			if($amt >= 0) {
				$this->add_time($amt);
			} else {
				$this->subtract_time($amt);
			}	
		}

		/*
		 * Creates a document for added time, transfers it from the TIME_GENERATOR to
		 * the user, then to the TIME_LOG, and updates the user balance.
		 */
		private function add_time($amt) {
			$record = array('amount' => $amt);
			$this->COLLECTION->insert($record);
			$this->TIME_GENERATOR = $this->COLLECTION->findOne(array('user' => 'TIME_GENERATOR'));
			$this->TIME_LOG = $this->COLLECTION->findOne(array('user' => 'TIME_LOG'));
			file_get_contents(BpiMongoConn::$TRANS_SERVER . "method=addItemToSubAccount&subId0=" 
				. $this->TIME_GENERATOR['time_subaccount'] . "&itemId=" . $record['_id'] );
			$this->add_time_transaction($record['_id']);
			$this->log_time_transaction($record['_id'], $this->TIME_LOG);
			$this->update_balance($amt);
		}

		/*
		 * Decreases the user's time balance, creates a document for the amount subtracted,
		 * and sends it from the user account to the TIME_LOG.
		 */
		private function subtract_time($amt) {
			$record_id = $this->split_time($amt);
			$this->log_time_transaction($record_id, $this->TIME_LOG);
		}

		/*
		 * Decreases the user's time balance and creates a document for the decreased amount
		 * in the user's subaccount
		 */
		private function split_time($amt) {
			if($this->account['time_balance'] < $amt) {
				echo "Insufficient funds";
				return NULL;
			} else {
				$record = array('amount' => $amt);
				$this->COLLECTION->insert($record);
				$this->TIME_LOG = $this->COLLECTION->findOne(array('user' => 'TIME_LOG'));
				file_get_contents(BpiMongoConn::$TRANS_SERVER . "method=addItemToSubAccount&subId0=" 
					. $this->account['time_subaccount'] . "&itemId=" . $record['_id'] );
				$this->update_balance($amt);
				return $record['_id'];			
			}
		}

		/*
		 * Transfers a document from the TIME_GENERATOR to the user's account
		 */
		private function add_time_transaction($record_id) {
	        $request = BpiMongoConn::$TRANS_SERVER . "method=transaction&id0=" 
	         . $this->account['account'] . "&id1=" . $this->TIME_GENERATOR['account']
        	 . "&subId0=" . $this->account['time_subaccount'] . "&subId1=" 
        	 . $this->TIME_GENERATOR['time_subaccount'] . "&itemsId0={\"items\":[\"empty\"]}"
       		 . "&itemsId1={\"items\":[\"empty\",\"" . $record_id . "\"]}";
	        $result = file_get_contents($request);
	  		BpiMongoConn::has_error($result, "Network error adding time request");
		}

		/*
		 * Transfers a document from the user's account to the specified log
		 */
		private function log_time_transaction($record_id, $log) {
			$request = BpiMongoConn::$TRANS_SERVER . "method=transaction&id0=" 
	         . $this->account['account'] . "&id1=" . $log['account']
        	 . "&subId0=" . $this->account['time_subaccount'] . "&subId1=" 
        	 . $log['time_subaccount'] . "&itemsId0={\"items\":[\"empty\",\"" . $record_id . "\"]}"
        	 . "&itemsId1={\"items\":[\"empty\"]}";
        	$result = file_get_contents($request);
	        BpiMongoConn::has_error($result, "Network error logging time request");
		}

		/*
		 * Updates the time balance field in the user's main document.
		 */
		private function update_balance($amt) {
			$this->account['time_balance'] += $amt; 
			$this->COLLECTION->update(array('user' => $this->user), $this->account);
		}

		/*
		 * Creates a document in the specified collection and a transaction server USER account 
		 * and subaccount with the specified name.
		 */
		private function add_user() {
			$acct_id = $this->add_trans_server_account($this->user, false);
			$subacct_id = $this->add_trans_server_subaccount($acct_id);
			$new_account = array('user' => $this->user, 'time_balance' => 0, 
				'account' => $acct_id, 'time_subaccount' => $subacct_id);
			$this->COLLECTION->insert($new_account);
			return true;
		}

		/*
		 * Creates a document in the specified collection and a transaction server GLOBAL account 
		 * and subaccount with the specified name.
		 */
		private static function add_global_account($name, $coll) {
			$acct_id = BpiMongoConn::add_trans_server_account($name, true);
			$subacct_id = BpiMongoConn::add_trans_server_subaccount($acct_id);
			$record = array('user' => $name, 'account' => $acct_id, 'time_subaccount' => $subacct_id);
			$coll->insert($record);
		}

		/* 
		 * Creates a transaction server account with the name specified. Creates a GLOBAL account with 
		 * the given name if $global is true, and creates a USER account otherwise.
		 */
		private static function add_trans_server_account($name, $global) {
			if($global) {
				$acct_string = file_get_contents(BpiMongoConn::$TRANS_SERVER . "method=addGlobalAccount&name=" . $name);
			} else {
				$acct_string = file_get_contents(BpiMongoConn::$TRANS_SERVER . "method=addAccount&username=" . $name);
			}
			$acct = json_decode($acct_string);
			if(BpiMongoConn::has_error($acct_string, "Network error adding new user account")) return;
			return $acct->{"content"}->{"_id"};
		}

		/*
		 * Creates a new subaccount in the transaction server for the specified account.
		 */
		private static function add_trans_server_subaccount($acct_id) {
			$subacct_string = file_get_contents(BpiMongoConn::$TRANS_SERVER . "method=addSubAccount&id0=" . $acct_id);	
			$subacct = json_decode($subacct_string);
			if(BpiMongoConn::has_error($subacct_string, "Network error adding new user subaccount")) return;
			return $subacct->{"content"}->{"_id"};
		}

		/*
		 * Checks whether the transaction server response specifies an error.
		 */
		private static function has_error($response, $message) {
			$r = json_decode($response);
			if($r->{"status"} != "success") {
				echo $message;
				return true;
			} else {
				echo "success";
				return false;
			}
		}
	}
?>