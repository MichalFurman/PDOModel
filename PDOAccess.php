<?php
	/**
	* 	Base class for PDO
	*/

    namespace mfurman\pdomodel;

	use Exception;
	use PDO;

	class PDOAccess implements idbAccess
	{
		protected $db = null;
		protected $config = [];

		protected $loggerDir = __DIR__ ."/errors";

    	public static function get ($config=null) 
		{ 
            static $inst = null;
            if ($inst === null) {
                $inst = new PDOAccess($config);
            }
            return $inst;
        }

	
	    function __construct($config = null) 
	    {

	        if ($config === null) {
                    global $global_conf;
                    isset($global_conf) && is_array($global_conf) ? $config = $global_conf : null;
                }

			if (!isset($config) || !is_array($config)) exit ('Error - no config for connection.');
			if (!isset($config['DB_HOST'])) exit ('Error - no host name in config.');
			if (!isset($config['DB_DATABASE'])) exit ('Error - no database name in config.');
			if (!isset($config['DB_USERNAME'])) exit ('Error - no user name in config.');
			if (!isset($config['DB_PASSWORD'])) exit ('Error - no user password.');		
			isset($config['LOGGER_DIR']) ? $this->loggerDir = __DIR__ .$config['LOGGER_DIR'] : null;		

			$this->config = $config;

			try {
				$this->db = @new PDO(
					$config['DB_CONNECTION'].':dbname=' . $config['DB_DATABASE'] . ';host=' . $config['DB_HOST'],
					$config['DB_USERNAME'],
					$config['DB_PASSWORD'],
					array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION )
				);
			}
			catch (Exception $e) {
				$this->logger($e);
				throw new Exception('No connection with database.<br>Send information to administrator.<br>Error: '.$e->getMessage());
			}
			
			$this->db->exec("set names utf8");
		}

		public function parse_in(string $string, bool $tags = true) :string
		{
			$string = str_replace(array('\r\n',"\\r\\n",'\r','\n',"\\r","\\n", '\x00',"\\x00",'\x1a',"\\x1a",'\\'), '', $string);
			($tags === true) ? $string = strip_tags($string) : null;
			$string = preg_replace('/\s+/', ' ', $string);
			$string = ltrim(rtrim($string));
			return $string;
		}


		public function log_rec(string $sql_string, bool $commit = true, string $log_table = 'general_log') :void
		{	
			$this->check_connect();
			$user_id = $_SESSION['user_id'] ? $_SESSION['user_id'] : 'unknown user id';
			$user_name = $_SESSION['user_name'] ? $_SESSION['user_name'] : 'unknown user name';
			$type = strstr($sql_string, ' ', true);			
			$stamp = 'INSERT INTO '.$log_table.' (user_id, user_name, method, command) VALUES ('.$user_id.',"'.$user_name.'",?,?)';
			
			$this->lock_tables($log_table, 'WRITE');
			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($stamp);
				$query->execute([$type, $sql_string]);
				if ($commit === true) $this->db->commit();
				$sql_string = null;
			}
			catch(Exception $e){
		  		$this->db->rollBack();
				$this->logger($e);
		  		throw new Exception('There was error in the method LOG_REC: '.$log_table.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$stamp);
		  	}		
		}

		public function lock_tables(mixed $db_tab_array, string $type = "WRITE") :void
		{	
			$this->check_connect();

			if (is_array($db_tab_array)) {
				$db_tabs = '';
				foreach ($db_tab_array as $key =>$value){
					$db_tabs .= '`'.$value.'` '.$type.', ';
				}
				$db_tabs = substr($db_tabs,0,-2)." ";
				$this->db->exec('LOCK TABLES '.$db_tabs);
			}
			else{
				$this->db->exec('LOCK TABLE `'.$db_tab_array.'` '.$type);	
			}
		}

		public function unlock_tables() :void
		{	
			$this->check_connect();
			$this->db->exec('UNLOCK TABLES');
		}

		public function begin() :void
		{
			$this->check_connect();
			$this->db->beginTransaction();
		}

		public function commit() :void
		{
			$this->check_connect();
			$this->db->commit();
			$this->unlock_tables();
		}
		
		public function select(string $db_tab, mixed $select='*', string $where = null , string $order = null, int $limit = null, bool $commit = true, string $lock = null) :array
		{
			$this->check_connect();
			if (!$this->exists_table($db_tab, $commit)) exit ('There is no that table in database: '.$db_tab);
			$columns = "";
			if (is_array($select)) {
				
				foreach ($select as $key =>$value){
					if (!$this->exists_columns($db_tab, array($value))) exit ('Column: "'.$value.'" not exists in this table:'.$db_tab);
					$columns .= $value.', ';				
				}
				$columns = substr($columns,0,-2)." ";
			}
			else {
				if ($select != '*' && !$this->exists_columns($db_tab, array($select))) exit ('Column: "'.$select.'" not exists in this table:'.$db_tab);
				$columns = $select;
			}

			$sql = 'SELECT '.$columns.' FROM '.$db_tab;

			if ($where !== null){
				$sql.=' WHERE '.$where;
			}
			if ($order !== null){
				$sql.=' ORDER BY '.$order;
			}
			if ($limit !== null){
				$sql.=' LIMIT '.$limit;
			}
			if ($lock !== null){
				$sql.=' '.$lock;
			}
			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($sql);
				$query->execute();
				if ($commit === true) $this->db->commit();
				$sql = null;

				$result = $query->fetchAll(PDO::FETCH_ASSOC);
				return $result;
			  }
			catch(Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
				$this->logger($e);
				throw new Exception('There was error in the method SELECT in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$sql);
		  	}
		}
		
		public function insert(string $db_tab, array $data, bool $parse = true, bool $commit = true, bool $log_rec = true) :int
		{
			$this->check_connect();
			if (!$this->exists_table($db_tab, $commit)) exit ('There is no that table in database: '.$db_tab);
			$values = [];
			$sql = 'INSERT INTO '.$db_tab.' (';
			foreach ($data as $key =>$value){
				$sql .= $key.', ';
			}
			$sql = substr($sql,0,-2).') VALUES (';
			$logsql = $sql;

			foreach ($data as $key =>$value){
				if ($value === null) $sql .= "NULL ,";
				else {
					$sql .= '?, ';
					$parse === true ? $value = $this->parse_in($value) : null;
					$values[] = $value;
					$logsql .= '"'.$value.'", ';
				}
			}
			$sql = substr($sql,0,-2).')';
			$logsql = substr($logsql,0,-2).')';

			try {
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($sql);
				$query->execute($values);
				$last_id = $this->db->lastInsertId(); 
				if ($commit === true) $this->db->commit();
				if ($log_rec === true) $this->log_rec($logsql, $commit);
				$sql = null;
				$logsql = null;

				return $last_id;
			}
			catch(Exception $e) {
				if ($this->db->inTransaction()) $this->db->rollBack();
			  	$this->db->exec('UNLOCK TABLES');
				$this->logger($e);
		  		throw new Exception('Wystąpił błąd wykonania INSERT in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$sql);
		  	}
		}

		public function update (string $db_tab, array $data, int $id, bool $parse = true, bool $commit = true, bool $log_rec = true) :bool
		{
			$this->check_connect();
			if (!$this->exists_table($db_tab, $commit)) exit ('There is no that table in database: '.$db_tab);
			$values = [];
			$sql = 'UPDATE '.$db_tab.' SET ';
			$logsql = $sql;
			foreach ($data as $key =>$value){
				if ($value === null) $sql .= $key." = NULL ,";
				else {					
					$sql .= $key.' = ?, ';
					$parse === true ? $value = $this->parse_in($value) : null;
					$values[] = $value;
					$logsql .= $key.' = "'.$value.'" ,';			
				}
			}
			$sql = substr($sql,0,-2).' WHERE id = '.$id;
			$logsql = substr($logsql,0,-2).' WHERE id = '.$id;

			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($sql);
				$query->execute($values);
				if ($commit === true) $this->db->commit();
				if ($log_rec === true) $this->log_rec($logsql, $commit);
				$sql = null;
				$logsql = null;

				return true;
			}
			catch(Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
				$this->db->exec('UNLOCK TABLES');
				$this->logger($e);
				throw new Exception('There was error in the method UPDATE in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$sql);
		  	}
		}

		public function update_where (string $db_tab, array $data, string $where, bool $parse = true, bool $commit = true, bool $log_rec = true) :bool
		{
			$this->check_connect();
			if (!$this->exists_table($db_tab, $commit)) exit ('There is no that table in database: '.$db_tab);
			$values = [];
			$sql = 'UPDATE '.$db_tab.' SET ';
			$logsql = $sql;
			foreach ($data as $key =>$value){
				if ($value === null) $sql .= $key." = NULL ,";
				else {
					$sql .= $key.' = ?, ';
					$parse === true ? $value = $this->parse_in($value) : null;
					$values[] = $value;
					$logsql .= $key.' = "'.$value.'" ,';			
				}
			}
			$sql = substr($sql,0,-2).' WHERE '.$where;
			$logsql = substr($logsql,0,-2).' WHERE '.$where;

			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($sql);
				$query->execute($values);
				if ($commit === true) $this->db->commit();
				if ($log_rec === true) $this->log_rec($logsql, $commit);
				$sql = null;
				$logsql = null;

				return true;
			}
			catch(Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
			    $this->db->exec('UNLOCK TABLES');
				$this->logger($e);
				throw new Exception('There was error in the method UPDATE_WHERE in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$sql);
		  	}
		}

		public function delete_where(string $db_tab, string $where, bool $commit = true, bool $log_rec = true) :bool
		{
			$this->check_connect();
			if (!$this->exists_table($db_tab, $commit)) exit ('There is no that table in database: '.$db_tab);
			$sql = 'DELETE FROM '.$db_tab.' WHERE '.$where;

			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($sql);
				$query->execute();
				if ($commit === true) $this->db->commit();
				if ($log_rec === true) $this->log_rec($sql, $commit);
				$sql = null;

				return true;
			}
			catch(Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
				$this->db->exec('UNLOCK TABLES');
				$this->logger($e);
				throw new Exception('There was error in the method DELETE_WHERE in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$sql);
		  	}
		}

		public function exists(string $db_tab, array $data = null, string $where = null, bool $commit = true) :bool
		{
			$this->check_connect();
			if (!isset($db_tab) || ($data === null && $where === null)) 
				exit ('Bad method parameter: "exist". Check parametres.');

			$values = [];
			$sql = 'SELECT * FROM '.$this->parse_in($db_tab).' WHERE ';
			if ($data !== null) {
				foreach ($data as $key =>$value){
					if ($value === null) $sql .= $key." IS NULL AND ";
					else {					
						$sql .= $key.' = ? AND ';
						$value = $this->parse_in($value);
						$values[] = $value;
					}
				}	
				if ($where === null) $sql = substr($sql,0,-5);
				else $sql = substr($sql,0,-5).' '.$where;
			}
			else $sql .= $where;

			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($sql);
				$query->execute($values);
				if ($commit === true) $this->db->commit();
				$sql = null;

				if ($query->rowCount() > 0 || $query->fetchColumn() > 0) return true;
				else return false;
			}
			catch(Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
				$this->db->exec('UNLOCK TABLES');
				$this->logger($e);
				throw new Exception('There was error in the method EXIST in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine());
			}	
		}
		
		public function count(string $db_tab, array $data = null, string $where = null, bool $commit = true) :int
		{
			$this->check_connect();
			if (!isset($db_tab) || ($data === null && $where === null)) 
				exit ('Bad method parameter: "count". Check parametres.');

			$values = [];
			$sql = 'SELECT * FROM '.$db_tab.' WHERE ';
			if ($data !== null) {
				foreach ($data as $key =>$value){
					if ($value === null) $sql .= $key." IS NULL AND ";
					else {					
						$sql .= $key.' = ? AND ';
						$value = $this->parse_in($value);
						$values[] = $value;
					}
				}	
				if ($where === null) $sql = substr($sql,0,-5);
				else $sql = substr($sql,0,-5).' '.$where;
			}
			else $sql .= $where;

			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($sql);
				$query->execute($values);
				if ($commit === true) $this->db->commit();
				$sql = null;

				return $query->rowCount();
			}
			catch(Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
				$this->db->exec('UNLOCK TABLES');
				$this->logger($e);
				throw new Exception('There was error in the method COUNT in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine());
			}	
		}		

		public function flat_array(array $array, $columns = null) :array
		{ 
		  	if ($columns == null || !is_array($columns)) $result = call_user_func_array('array_merge', $array);
		  	else {
 			  	$result = array();
			  	foreach ($array as $array_w) { 
			  		foreach ($columns as $columns_w) {
			  			$result[] = $array_w[$columns_w];
			  		}
			  	}
			}
		  	return $result; 
		} 
	
		public function array_column(array $input, $columnKey, $indexKey = null) :mixed
		{
			$array = array();
			foreach ($input as $value) {
				if ( !array_key_exists($columnKey, $value)) {
					trigger_error("Key \"$columnKey\" not exists in table");
					return false;
				}
				if (is_null($indexKey)) {
					$array[] = $value[$columnKey];
				}
				else {
					if ( !array_key_exists($indexKey, $value)) {
						trigger_error("Key \"$indexKey\" not exists in table");
						return false;
					}
					if ( ! is_scalar($value[$indexKey])) {
						trigger_error("Key \"$indexKey\" not exists in table");
						return false;
					}
					$array[$value[$indexKey]] = $value[$columnKey];
				}
			}
			return $array;
		}

		public function list_tables(array $columns = null, array $filtres_yes = null, array $filtres_no = null, bool $commit = true) :array
		{
			$this->check_connect();
	        $sql = 'show full tables';
			if ($columns != null && is_array($columns)) {
				$col_names = '';
				foreach ($columns as $key) {
					$col_names .= '"'.$key.'" ,';
				}
				$col_names = substr($col_names,0,-2);
				$sql = 'SELECT DISTINCT TABLE_NAME
    						FROM INFORMATION_SCHEMA.COLUMNS
    						WHERE COLUMN_NAME IN ('.$col_names.')
        				AND TABLE_SCHEMA="'.$this->config['DB_DATABASE'].'"';
			}
			$query = $this->db->query($sql);
			
			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($sql);
				$query->execute();
				if ($commit === true) $this->db->commit();
				$sql = null;
				$all_list = $query->fetchAll(PDO::FETCH_COLUMN);
				$return_list = $all_list;
			}
			catch(Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
				$this->db->exec('UNLOCK TABLES');
				$this->logger($e);
				throw new Exception('There was error in the method LIST_TABLES in : <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine());
			}	
			
			if ($filtres_yes !== null && is_array($filtres_yes)) {
				foreach ($filtres_yes as $filter) {
					if ($filter !== null) {
						if (!empty($return_list)) {
									$all_list = $return_list;
									$return_list = array();
						}
						foreach ($all_list as $value) if (strstr($value, $filter)) $return_list[] = $value;		
					}
				}
			}

			if ($filtres_no !== null && is_array($filtres_no)) {
				foreach ($filtres_no as $filter) {
					if ($filter !== null) {
						if (!empty($return_list)) {
									$all_list = $return_list;
									$return_list = array();
						}
						foreach ($all_list as $value) if (!strstr($value, $filter)) $return_list[] = $value;		
					}
				}
			}

			return $return_list;
		}

		public function list_columns(string $table = null, array $props = null, bool $commit = true) :mixed
		{
      		    if (!isset($table))
        		exit ('Bad method parameter: "list_columns". Check parametres.');

			$sql = 'SHOW COLUMNS FROM '.$table;
			if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
			$query = $this->db->prepare($sql);
			$query->execute();
			if ($commit === true) $this->db->commit();
			$columns = $query->fetchAll(PDO::FETCH_ASSOC);

			if (is_array($props)) {
				foreach ($columns as $column) {
					foreach ($props as $prop){
						if (array_key_exists($prop, $column)) $row[$prop] = $column[$prop];
					}
					$result[] = $row;
				}
			}
			else $result = $columns;
			if (!empty($result)) return $result;
			else return false;
		}

		public function exists_columns(string $table = null, array $columns = null, mixed $props = null, bool $commit = true) :mixed
		{
      		if (!isset($table) || !isset($columns))
        		exit ('Bad method parameter: "exists_columns". Check parametres.');
		
			if (is_array($columns)) {
				$col_names = '';
				foreach ($columns as $column) {
					$sql = 'SHOW COLUMNS FROM '.$table.' WHERE Field = "' . $column . '"';
					if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
					$query = $this->db->prepare($sql);
					$query->execute();
					if ($commit === true) $this->db->commit();
					$field = $this->flat_array($query->fetchAll(PDO::FETCH_ASSOC),0);
					if (empty($field)) continue;
					
					if (is_array($props)) {
						$row = null;
						foreach ($props as $prop){
							if (array_key_exists($prop, $field)) $row[$prop] = $field[$prop];
						}
						$result[] = $row;
					}
					else if (strtolower($props) == 'name'){
						$result[] = $field['Field'];
					}
					else $result[] = $field; 
					$query = null;
				}
				if (!empty($result)) return $result;
				else return false;
			}
			else {
				$sql = 'SHOW COLUMNS FROM '.$table.' WHERE Field = "' . $columns . '"';
				$this->db->beginTransaction();
				$query = $this->db->prepare($sql);
				$query->execute();
				$this->db->commit();
				$field = $this->flat_array($query->fetchAll(PDO::FETCH_ASSOC),0);
				
				if (is_array($props)) {
					$row = null;
					foreach ($props as $prop){
						if (array_key_exists($prop, $field)) $row[$prop] = $field[$prop];
					}
					$result = $row;
				}
				else if (strtolower($props) == 'name'){
					$result = $field['Field'];
				}
				else $result = $field;
				
				if (!empty($result)) return $result;
				else return false;
			}
		}

		public function logger(Exception $e, int $logType = 3) :void
		{
			$log  =  "Time:  ".date('h:i:s')."   | Error: ".$e->getMessage()."\n";
			$log .=  "Stack trace: \n".$e->getTrace();
			error_log($log, $logType, $this->loggerDir.'/'.date('Y-m-d').'log');
		} 

		private function exists_table(string $source, bool $commit) 
		{
			return (in_array($source, $this->list_tables($columns = null, $filtres_yes = null, $filtres_no = null, $commit)));
		}

		private function check_connect() :void
		{
			if ($this->db === null) exit ('No database connection');
		}
	}

?>