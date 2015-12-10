<?php
	class setup_model extends model {
		/* Determine next step
		 */
		public function step_to_take() {
			$missing = $this->missing_php_extensions();
			if (count($missing) > 0) {
				return "php_extensions";
			}

			if ($this->db->connected == false) {
				$db = new MySQLi_connection(DB_HOSTNAME, DB_DATABASE, DB_USERNAME, DB_PASSWORD);
			} else { 
				$db = $this->db;
			}

			if ($db->connected == false) {
				/* No database connection
				 */
				if ((DB_HOSTNAME == "localhost") && (DB_DATABASE == "banshee") && (DB_USERNAME == "banshee") && (DB_PASSWORD == "banshee")) {
					return "db_settings";
				}

				return "create_db";
			}

			$result = $db->execute("show tables like %s", "settings");
			if (count($result) == 0) {
				return "import_sql";
			}

/*
			$settings = new settings($db);
			if ($settings->database_version != null) {
				return "update_db";
			}
*/

			return "done";
		}

		/* Missing PHP extensions
		 */
		public function missing_php_extensions() {
			static $missing = null;

			if ($missing !== null) {
				return $missing;
			}

			$missing = array();
			foreach (array("libxml", "mysqli", "xsl") as $extension) {
				if (extension_loaded($extension) == false) {
					array_push($missing, $extension);
				}
			}

			return $missing;
		}

		/* Remove datase related error messages
		 */
		public function remove_database_errors() {
			$errors = explode("\n", rtrim(ob_get_contents()));
			ob_clean();

			foreach ($errors as $error) {
				if (strtolower(substr($error, 0, 14)) != "mysqli_connect") {
					print $error;
				}
			}
		}

		/* Create the MySQL database
		 */
		public function create_database($username, $password) {
			$db = new MySQLi_connection(DB_HOSTNAME, "mysql", $username, $password);

			if ($db->connected == false) {
				$this->output->add_message("Error connecting to database.");
				return false;
			}

			$db->query("begin");

			/* Create database
			 */
			$query = "create database if not exists %S character set utf8";
			if ($db->query($query, DB_DATABASE) == false) {
				$db->query("rollback");
				$this->output->add_message("Error creating database.");
				return false;
			}

			/* Create user
			 */
			$query = "select count(*) as count from user where User=%s";
			if (($users = $db->execute($query, DB_USERNAME)) === false) {
				$db->query("rollback");
				$this->output->add_message("Error checking for user.");
				return false;
			}

			if ($users[0]["count"] == 0) {
				$query = "create user %s@%s identified by %s";
				if ($db->query($query, DB_USERNAME, DB_HOSTNAME, DB_PASSWORD) == false) {
					$db->query("rollback");
					$this->output->add_message("Error creating user.");
					return false;
				}
			} else {
				$login_test = new MySQLi_connection(DB_HOSTNAME, DB_DATABASE, DB_USERNAME, DB_PASSWORD);
				if ($login_test->connected == false) {
					$db->query("rollback");
					$this->output->add_message("Invalid credentials in settings/website.conf.");
					return false;
				}
			}

			/* Set access rights
			 */
			$rights = array(
				"select", "insert", "update", "delete",
				"create", "drop", "alter", "index", "lock tables",
				"create view", "show view");

			$query = "grant ".implode(", ", $rights)." on %S.* to %s@%s";
			if ($db->query($query, DB_DATABASE, DB_USERNAME, DB_HOSTNAME) == false) {
				$db->query("rollback");
				$this->output->add_message("Error setting access rights.");
				return false;
			}

			/* Commit changes
			 */
			$db->query("commit");
			$db->query("flush privileges");
			unset($db);

			return true;
		}

		/* Import database tables from file
		 */
		public function import_sql() {
			system("mysql -u \"".DB_USERNAME."\" -h \"".DB_HOSTNAME."\" --password=\"".DB_PASSWORD."\" \"".DB_DATABASE."\" < ../database/mysql.sql", $result);
			if ($result != 0) {
				$this->output->add_message("Error while importing database tables.");
				return false;
			}

			$this->db->query("update users set status=%d", USER_STATUS_CHANGEPWD);
			$this->settings->secret_website_code = random_string();

			return true;
		}

		/* Update database
		 */
		public function update_database() {
			$settings = new settings($this->db);

			return true;
		}

		/* Add setting when missing
		 */
		private function ensure_setting($key, $type, $value) {
			if ($this->db->entry("settings", $key, "key") != false) {
				return true;
			}

			$entry = array(
				"key"   => $key,
				"type"  => $type,
				"value" => $value);
			return $this->db->insert("settings", $entry) !== false;
		}

		/* Ensure settings
		 */
		public function ensure_settings() {
			$this->ensure_setting("hiawatha_cache_enabled", "boolean", "false");
			$this->ensure_setting("hiawatha_cache_default_time", "integer", "3600");
			$this->ensure_setting("session_timeout", "integer", "3600");
			$this->ensure_setting("session_persistent", "boolean", "false");
		}
	}
?>
