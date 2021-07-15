<?php
	/**
	* 	Bazowa klasa dla PDO,
	*       Jest to klasa dodana do projektu z własnego repozytorium.
	*       Klasa generuje jedyną instancję, singletone połączenia z bazą danych.
	*       W każdym momencie w aplikacji możemy mieć do niej dostęp przez statyczną metodę: get()
	*/

    namespace Myvendor\Actaskman\Models;

	use PDO;

	class PDOAccess
	{

	protected $db;

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

			try {
				$this->db = @new PDO(
					$config['DB_CONNECTION'].':dbname=' . $config['DB_DATABASE'] . ';host=' . $config['DB_HOST'],
					$config['DB_USERNAME'],
					$config['DB_PASSWORD'],
					array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION )
				);
			}
			catch (\PDOException $e) {
				exit('No connection with database.<br>Send information to administrator.<br>Nr błędu: '.$e->getMessage());
			}
			
			$this->db->exec("set names utf8");
		}

		public function parse_in(string $string, $tags=true) :string
		{
			$string = str_replace(array('\r\n',"\\r\\n",'\r','\n',"\\r","\\n", '\x00',"\\x00",'\x1a',"\\x1a",'\\'), '', $string);
			($tags === true) ? $string = strip_tags($string) : null;
			$string = preg_replace('/\s+/', ' ', $string);
			$string = ltrim(rtrim($string));
			return $string;
		}


		public function log_rec ($sql_string, $commit=true, $log_table='general_log') :void
		{	
			$wpissql = $sql_string;
			$typ_operacji = strstr($wpissql, ' ', true);
			$stamp = 'INSERT INTO '.$log_table.' (user_id, user_name, method, command) VALUES ('.$_SESSION['user_id'].',"'.$_SESSION['user_nazwa'].'",?,?)';
			
			$this->lock_tables($log_table, 'WRITE');
			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($stamp);
				$query->execute([$typ_operacji,$wpissql]);
				if ($commit === true) $this->db->commit();
				$wpissql = null;
			}
			catch(\Exception $e){
		  		$this->db->rollBack();
		  		exit('We have errors in the method LOG_REC: '.$log_table.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$stamp);
		  	}		
		}

		public function lock_tables ($db_tab_array, $type="WRITE") :void
		{	
			if (is_array($db_tab_array)) {
				$db_tabs = '';
				foreach ($db_tab_array as $klucz =>$wartosc){
					$db_tabs .= '`'.$wartosc.'` '.$type.', ';
				}
				$db_tabs = substr($db_tabs,0,-2)." ";
	
				
				$this->db->exec('LOCK TABLES '.$db_tabs);
			}
			else{
				// echo('LOCK TABLE '.$db_tab_array);	
				$this->db->exec('LOCK TABLE `'.$db_tab_array.'` '.$type);	
			}
		}

		public function unlock_tables () :void
		{	
			$this->db->exec('UNLOCK TABLES');
		}

		public function begin() :void
		{
			$this->db->beginTransaction();
		}

		public function commit() :void
		{
			$this->db->commit();
			$this->unlock_tables();
		}
		
		public function select (string $db_tab, $select='*', string $where=null , string $order=null, int $limit=null, bool $commit=true, string $lock=null) :array
		{
			if (!$this->exist_table($db_tab, $commit)) exit ('There is no that table in database: '.$db_tab);
			$columns = "";
			if (is_array($select)) {
				
				foreach ($select as $klucz =>$wartosc){
					if (!$this->exist_columns($db_tab, array($wartosc))) exit ('Column: "'.$wartosc.'" not exists in this table:'.$db_tab);
					$columns .= $wartosc.', ';				
				}
				$columns = substr($columns,0,-2)." ";
			}
			else {
				if ($select != '*' && !$this->exist_columns($db_tab, array($select))) exit ('Column: "'.$select.'" not exists in this table:'.$db_tab);
				$columns = $select;
			}

			$wpissql = 'SELECT '.$columns.' FROM '.$db_tab;

			if ($where !== null){
				$wpissql.=' WHERE '.$where;
			}
			if ($order !== null){
				$wpissql.=' ORDER BY '.$order;
			}
			if ($limit !== null){
				$wpissql.=' LIMIT '.$limit;
			}
			if ($lock !== null){
				$wpissql.=' '.$lock;
			}
			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($wpissql);
				$query->execute();
				if ($commit === true) $this->db->commit();
				$wpissql = null;
			}
			catch(\Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
		  		exit('We have errors in the method SELECT in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$wpissql);
		  	}
		  	$result = $query->fetchAll(\PDO::FETCH_ASSOC);
		  	return $result;
		}
		
		public function insert (string $db_tab, array $wpis, bool $parse=true, bool $commit=true, bool $log_rec=true) :int
		{
			if (!$this->exist_table($db_tab, $commit)) exit ('There is no that table in database: '.$db_tab);
			$wartosci = array();
			$wpissql = 'INSERT INTO '.$db_tab.' (';
			foreach ($wpis as $klucz =>$wartosc){
				$wpissql .= $klucz.', ';
			}
			$wpissql = substr($wpissql,0,-2).') VALUES (';
			$logsql = $wpissql;

			foreach ($wpis as $klucz =>$wartosc){
				if ($wartosc === null) $wpissql .= "NULL ,";
				else {
					$wpissql .= '?, ';
					$parse === true ? $wartosc = $this->parse_in($wartosc) : null;
					$wartosci[] = $wartosc;
					$logsql .= '"'.$wartosc.'", ';
				}
			}
			$wpissql = substr($wpissql,0,-2).')';
			$logsql = substr($logsql,0,-2).')';

			try {
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($wpissql);
				$query->execute($wartosci);
				$last_id = $this->db->lastInsertId(); 
				if ($commit === true) $this->db->commit();
				if ($log_rec === true) $this->log_rec($logsql, $commit);
				$wpissql = null;
				$logsql = null;
				return $last_id;
			}
			catch (\Exception $e) {
				if ($this->db->inTransaction()) $this->db->rollBack();
			  	$this->db->exec('UNLOCK TABLES');
		  		exit('Wystąpił błąd wykonania INSERT in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$wpissql);
		  	}
		}

		public function update (string $db_tab, array $wpis, int $id, bool $parse=true, bool $commit=true, bool $log_rec=true) :void
		{
			if (!$this->exist_table($db_tab, $commit)) exit ('There is no that table in database: '.$db_tab);
			$wartosci = array();
			$wpissql = 'UPDATE '.$db_tab.' SET ';
			$logsql = $wpissql;
			foreach ($wpis as $klucz =>$wartosc){
				if ($wartosc === null) $wpissql .= $klucz." = NULL ,";
				else {					
					$wpissql .= $klucz.' = ?, ';
					$parse === true ? $wartosc = $this->parse_in($wartosc) : null;
					$wartosci[] = $wartosc;
					$logsql .= $klucz.' = "'.$wartosc.'" ,';			
				}
			}
			$wpissql = substr($wpissql,0,-2).' WHERE id = '.$id;
			$logsql = substr($logsql,0,-2).' WHERE id = '.$id;

			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($wpissql);
				$query->execute($wartosci);
				if ($commit === true) $this->db->commit();
				if ($log_rec === true) $this->log_rec($logsql, $commit);
				$wpissql = null;
				$logsql = null;
			}
			catch(\Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
				$this->db->exec('UNLOCK TABLES');
		  		exit('We have errors in the method UPDATE in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$wpissql);
		  	}
		}

		public function update_where (string $db_tab, array $wpis, string $where, bool $parse=true, bool $commit=true, bool $log_rec=true) :void
		{
			if (!$this->exist_table($db_tab, $commit)) exit ('There is no that table in database: '.$db_tab);
			$wartosci = array();
			$wpissql = 'UPDATE '.$db_tab.' SET ';
			$logsql = $wpissql;
			foreach ($wpis as $klucz =>$wartosc){
				if ($wartosc === null) $wpissql .= $klucz." = NULL ,";
				else {
					$wpissql .= $klucz.' = ?, ';
					$parse === true ? $wartosc = $this->parse_in($wartosc) : null;
					$wartosci[] = $wartosc;
					$logsql .= $klucz.' = "'.$wartosc.'" ,';			
				}
			}
			$wpissql = substr($wpissql,0,-2).' WHERE '.$where;
			$logsql = substr($logsql,0,-2).' WHERE '.$where;

			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($wpissql);
				$query->execute($wartosci);
				if ($commit === true) $this->db->commit();
				if ($log_rec === true) $this->log_rec($logsql, $commit);
				$wpissql = null;
				$logsql = null;
			}
			catch(\Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
			    $this->db->exec('UNLOCK TABLES');
			  	exit('We have errors in the method UPDATE_WHERE in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$wpissql);
		  	}
		}

		public function delete_where(string $db_tab, string $where, bool $commit=true, bool $log_rec=true) :void
		{
			if (!$this->exist_table($db_tab, $commit)) exit ('There is no that table in database: '.$db_tab);
			$wpissql = 'DELETE FROM '.$db_tab.' WHERE '.$where;

			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($wpissql);
				$query->execute();
				if ($commit === true) $this->db->commit();
				if ($log_rec === true) $this->log_rec($wpissql, $commit);
				$wpissql = null;
			}
			catch(\Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
				$this->db->exec('UNLOCK TABLES');
		 	 	exit('We have errors in the method DELETE_WHERE in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine().'<br>in SQL string: '.$wpissql);
		  	}
		}

		public function exist(string $db_tab, array $wpis=null, string $where=null, bool $commit=true) :bool
		{
			if (!isset($db_tab) || ($wpis === null && $where === null)) 
				exit ('Bad method parameter: "exist". Check parametres.');

			$wartosci = array();
			$wpissql = 'SELECT * FROM '.$this->parse_in($db_tab).' WHERE ';
			if ($wpis !== null) {
				foreach ($wpis as $klucz =>$wartosc){
					if ($wartosc === null) $wpissql .= $klucz." IS NULL AND ";
					else {					
						$wpissql .= $klucz.' = ? AND ';
						$wartosc = $this->parse_in($wartosc);
						$wartosci[] = $wartosc;
					}
				}	
				if ($where === null) $wpissql = substr($wpissql,0,-5);
				else $wpissql = substr($wpissql,0,-5).' '.$where;
			}
			else $wpissql .= $where;

			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($wpissql);
				$query->execute($wartosci);
				if ($commit === true) $this->db->commit();
				$wpissql = null;
				if ($query->rowCount() > 0 || $query->fetchColumn() > 0) return true;
				else return false;
			}
			catch(\Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
				$this->db->exec('UNLOCK TABLES');
				exit('We have errors in the method EXIST in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine());
			}	
		}
		
		public function count(string $db_tab, array $wpis=null, string $where=null, bool $commit=true) 
		{
			if (!isset($db_tab) || ($wpis === null && $where === null)) 
				exit ('Bad method parameter: "count". Check parametres.');

			$wartosci = array();
			$wpissql = 'SELECT * FROM '.$db_tab.' WHERE ';
			if ($wpis !== null) {
				foreach ($wpis as $klucz =>$wartosc){
					if ($wartosc === null) $wpissql .= $klucz." IS NULL AND ";
					else {					
						$wpissql .= $klucz.' = ? AND ';
						$wartosc = $this->parse_in($wartosc);
						$wartosci[] = $wartosc;
					}
				}	
				if ($where === null) $wpissql = substr($wpissql,0,-5);
				else $wpissql = substr($wpissql,0,-5).' '.$where;
			}
			else $wpissql .= $where;

			try{
				if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
				$query = $this->db->prepare($wpissql);
				$query->execute($wartosci);
				if ($commit === true) $this->db->commit();
				$wpissql = null;
				return $query->rowCount();
			}
			catch(\Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
				$this->db->exec('UNLOCK TABLES');
				exit('We have errors in the method COUNT in '.$db_tab.': <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine());
			}	
		}		

		public function flat_array(array $array, $columns=null) :array
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
	
		public function array_column(array $input, $columnKey, $indexKey = null) :array
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

		public function list_tables(array $columns=null, array $filtres_yes=null, array $filtres_no=null, bool $commit=true) :array
		{
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
        				AND TABLE_SCHEMA="'.$this->pdo_details['db'].'"';
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
			catch(\Exception $e){
				if ($this->db->inTransaction()) $this->db->rollBack();
				$this->db->exec('UNLOCK TABLES');
				exit('We have errors in the method LIST_TABLES in : <br>' . $e->getMessage().'<br>Check error in line: '.$e->getLine());
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

		public function list_columns(string $table=null, array $props=null, bool $commit=true) 
		{
      		    if (!isset($table))
        		exit ('Bad method parameter: "list_columns". Check parametres.');

			$sql = 'SHOW COLUMNS FROM '.$table;
			if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
			$query = $this->db->prepare($sql);
			$query->execute();
			if ($commit === true) $this->db->commit();
			$columns = $query->fetchAll(\PDO::FETCH_ASSOC);

			if (is_array($props)) {
				foreach ($columns as $column) {
					foreach ($props as $prop){
						if (array_key_exists($prop,$column)) $row[$prop] = $column[$prop];
					}
					$result[] = $row;
				}
			}
			else $result = $columns;
			if (!empty($result)) return $result;
			else return false;
		}

		public function exist_columns(string $table=null, array $columns=null, array $props=null, bool $commit=true)
		{
      		if (!isset($table) || !isset($columns))
        		exit ('Bad method parameter: "exist_columns". Check parametres.');
		
			if (is_array($columns)) {
				$col_names = '';
				foreach ($columns as $column) {
					$sql = 'SHOW COLUMNS FROM '.$table.' WHERE Field = "' . $column . '"';
					if ($commit === true || !$this->db->inTransaction()) $this->db->beginTransaction();
					$query = $this->db->prepare($sql);
					$query->execute();
					if ($commit === true) $this->db->commit();
					$field = self::flat_array($query->fetchAll(\PDO::FETCH_ASSOC),0);
					if (empty($field)) continue;
					
					if (is_array($props)) {
						$row = null;
						foreach ($props as $prop){
							if (array_key_exists($prop,$field)) $row[$prop] = $field[$prop];
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
				$field = self::flat_array($query->fetchAll(\PDO::FETCH_ASSOC),0);
				
				if (is_array($props)) {
					$row = null;
					foreach ($props as $prop){
						if (array_key_exists($prop,$field)) $row[$prop] = $field[$prop];
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

		private function exist_table (string $source, bool $commit) 
		{
			return (in_array($source, $this->list_tables($columns=null, $filtres_yes=null, $filtres_no=null, $commit)));
		}

	}
