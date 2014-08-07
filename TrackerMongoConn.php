<?php

	/*
	 * Defines the class for manipulating the transaction server to log
	 * time for users, and spend their time balance on items.
	 */ 
	class TrackerMongoConn {
		
		private static $DB_NAME = "TimeTracker";
		private static $MONGO_SERVER = "mongodb://54.88.133.189/api/";
		private static $TRANS_SERVER = "http://54.88.133.189/api/transaction.cgi?";
		private static $COLL_NAME = "time";
		private $COLLECTION;
		private $TIME_LOG;
		private $DRINK_LOG;
		private $account;
		private $user;

		/* 
		 * Constructor connects to MongoDB server and adds user to the user collection
		 * and transaction server if existing not found 
		 */
		public function __construct($user) {
			$m = new MongoClient(TrackerMongoConn::$MONGO_SERVER);
			$db = $m->selectDB(TrackerMongoConn::$DB_NAME);
			$this->COLLECTION = $db->selectCollection(TrackerMongoConn::$COLL_NAME);
			$this->user = $user;
			$query = array('user' => $user);
			if (NULL == $this->COLLECTION->findOne($query)) {
				if(!$this->add_user()) {
					echo "\nError communicating with server to add user";
					return;
				}
			}
			$this->account = $this->COLLECTION->findOne($query);
			$this->TIME_LOG = $this->COLLECTION->findOne(array('user' => 'TIME_LOG'));
		}

		/* 
		 * Adds the two global users to the user collection and transaction server:
		 * 1) TIME_LOG: Stores time documents already added to user accounts
		 * 2) DRINK_LOG: Stores time documents sent from user account to drink 
		 */
		public static function initialize_time_tracker() {
			$m = new MongoClient(TrackerMongoConn::$MONGO_SERVER);
			$db = $m->selectDB(TrackerMongoConn::$DB_NAME);
			$coll = $db->selectCollection(TrackerMongoConn::$COLL_NAME);
			TrackerMongoConn::add_global_account("TIME_LOG", $coll);
			TrackerMongoConn::add_global_account("DRINK_LOG", $coll);
			TrackerMongoConn::add_global_account("MONSTER_LOG", $coll);
		}

		/*
		 * Adjusts user's time balance by the given hours and minutes.
		 */
		public static function track_time($hours, $minutes, $user) {
			$conn = new TrackerMongoConn($user);
			$total_min = ($hours * 60) + $minutes;
			$units = (int)($total_min / 6);
			if($total_min % 6 > 0) {
				$units += 1;
			} else if ($total_min % 6 < 0) {
				$units -= 1;
			}
			return $conn->adjust_time($units);
		}

		/*
		 * Adjusts user's time balance and logs a monster purchase.
		 */
		public static function purchase_monster($amt, $user) {
			$conn = new TrackerMongoConn($user);
			return $conn->purchase($amt, "MONSTER_LOG");
		}


		/*
		 * Decreases the user's time balance, and logs the transaction 
		 * in the appropriate log.
		 */
		public function purchase($amt, $log_name) {
			$log = $this->COLLECTION->findOne(array('user' => $log_name));
			if(NULL == $log) {
				echo "Log: " . $log_name . " not found.";
				return false;
			}
			$record = $this->split_time(-$amt);
			if(NULL != $record['_id']) {
				$this->log_time_transaction($record['_id'], $log);
				return true;
			} else return false;
		}

		/*
		 * Decreases the user's time balance, creates a document for the amount drank,
		 * and sends it from the user account to the DRINK_LOG.
		 */
		public function drink($amt) {
			$record = $this->split_time(-$amt);
			if(NULL != $record['_id']) {
				$this->DRINK_LOG = $this->COLLECTION->findOne(array('user' => 'DRINK_LOG'));
				$this->log_time_transaction($record['_id'], $this->DRINK_LOG);
				return true;
			} else return false;
		}

		/*
		 * Adjust the user's time balance and sends the change to the TIME_LOG.
		 */
		public function adjust_time($amt) {
			if($amt >= 0) {
				return $this->add_time($amt);
			} else {
				return $this->subtract_time($amt);
			}	
		}

		/*
		 * Returns the user account's time balance.
		 */
		public function get_time_balance() {
			return $this->account['time_balance'];
		}

		/*
		 * Creates a document for added time, transfers it to the TIME_LOG, 
		 * and updates the user balance.
		 */
		private function add_time($amt) {
			$record = $this->add_time_record($amt);
			$this->log_time_transaction($record['_id'], $this->TIME_LOG);
			$this->update_balance($amt);
			return true;
		}

		/*
		 * Decreases the user's time balance, creates a document for the amount subtracted,
		 * and sends it from the user account to the TIME_LOG.
		 */
		private function subtract_time($amt) {
			$record = $this->split_time($amt);
			if(NULL != $record['_id']) {
				$this->log_time_transaction($record['_id'], $this->TIME_LOG);
				return true;
			} else return false;
		}

		/*
		 * Decreases the user's time balance and creates a document for the decreased amount
		 * in the user's subaccount
		 */
		private function split_time($amt) {
			if($this->account['time_balance'] < -$amt) {
				return NULL; //Insufficient funds
			} else {
				$record = $this->add_time_record($amt);
				$this->update_balance($amt);
				return $record;			
			}
		}

		/* 
		 * Adds a time document to the user's account
		 */
		private function add_time_record($amt) {
			$record = array('amount' => $amt);
			$this->COLLECTION->insert($record);
			$result = file_get_contents(TrackerMongoConn::$TRANS_SERVER . "method=addItemToSubAccount&subId0=" 
				. $this->account['time_subaccount'] . "&itemId=" . $record['_id'] );
			return $record;
		}

		/*
		 * Transfers a document from the user's account to the specified log
		 */
		private function log_time_transaction($record_id, $log) {
			$request = TrackerMongoConn::$TRANS_SERVER . "method=transaction&id0=" 
	         . $this->account['account'] . "&id1=" . $log['account']
        	 . "&subId0=" . $this->account['time_subaccount'] . "&subId1=" 
        	 . $log['time_subaccount'] . "&itemsId0={\"items\":[\"empty\",\"" . $record_id . "\"]}"
        	 . "&itemsId1={\"items\":[\"empty\"]}";
        	$result = file_get_contents($request);
	        TrackerMongoConn::has_error($result, "Network error logging time request");
		}

		/*
		 * Updates the time balance field in the user's main document.
		 */
		private function update_balance($amt) {
			$this->account['time_balance'] += $amt; 
			echo $this->user . " updated balance: " . $this->account['time_balance'] . "<br>"; //remove ----------------------
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
			$acct_id = TrackerMongoConn::add_trans_server_account($name, true);
			$subacct_id = TrackerMongoConn::add_trans_server_subaccount($acct_id);
			$record = array('user' => $name, 'account' => $acct_id, 'time_subaccount' => $subacct_id);
			$coll->insert($record);
		}

		/* 
		 * Creates a transaction server account with the name specified. Creates a GLOBAL account with 
		 * the given name if $global is true, and creates a USER account otherwise.
		 */
		private static function add_trans_server_account($name, $global) {
			if($global) {
				$acct_string = file_get_contents(TrackerMongoConn::$TRANS_SERVER . "method=addGlobalAccount&name=" . $name);
			} else {
				$acct_string = file_get_contents(TrackerMongoConn::$TRANS_SERVER . "method=addAccount&username=" . $name);
			}
			$acct = json_decode($acct_string);
			if(TrackerMongoConn::has_error($acct_string, "Network error adding new user account")) return;
			return $acct->{"content"}->{"_id"};
		}

		/*
		 * Creates a new subaccount in the transaction server for the specified account.
		 */
		private static function add_trans_server_subaccount($acct_id) {
			$subacct_string = file_get_contents(TrackerMongoConn::$TRANS_SERVER . "method=addSubAccount&id0=" . $acct_id);	
			$subacct = json_decode($subacct_string);
			if(TrackerMongoConn::has_error($subacct_string, "Network error adding new user subaccount")) return;
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
				return false;
			}
		}
	}
?>