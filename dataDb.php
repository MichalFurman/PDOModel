<?php
    namespace mfurman\pdomodel;

	use http\Exception\InvalidArgumentException;

	
	class dataDb implements idataDb
	{
		
		private $dbaccess;
		private $data = array();
		private $commit = true;
		private $log_rec = true;

		function __construct(object $dbaccess, $commit=true, $log_rec=false) 
		{
      			if (!isset($dbaccess)) throw new InvalidArgumentException('Missing idatabase class - please check arguments of dependency injection.');
			$this->dbaccess = $dbaccess;
			$this->commit = $commit;
			$this->log_rec = $log_rec;
		}
		
		public function set_commit(bool $flag=true)
		{
			$this->commit = $flag;
		}

		public function reset()
		{
			$this->data = null;
			$this->data = array();
			return $this;
		}

		public function is()
		{
			
			if (empty($this->data)) return false;
			return true;
		}

		public function set($name, $value=null)
		{
			
			if (is_array($name)) {
				foreach ($name as $key => $value) {
					$this->data[$key] = $value;
				}
			}
			else {
				$this->data[$name] = $value;
			}
			return $this;
		}

		public function get($name=null, $index=0)
		{		
			$dimm = count($this->data);
			if ($name !== null && !is_array($name)) {
				if (isset($this->data[$index][$name])) return $this->data[$index][$name];
				else if (isset($this->data[$name])) return $this->data[$name];
				else return null;
			}
			else if ($name !== null && is_array($name)) {
				if ($dimm > 1) return $this->dbaccess->flat_array($this->data, $name);
				else {
					$result = array();
					foreach ($name as $key => $value) {
						if (isset($this->data[$index][$value])) $result[] = $this->data[$index][$value];
						else if (isset($this->data[$value])) $result[] = $this->data[$value];
					}
					return $result;
				}
			}
			else {
				return $this->data;
			}
		}

		public function get_flat ()
		{
			return $this->dbaccess->flat_array($this->get());
		}

		public function del($name=null, $index=0)
		{
			if ($name === null) {
				$this->data = null;
				$this->data = array();
			}
			else {
				if (is_array($name)) {
					if (isset($this->data[$index]) && is_array($this->data[$index])) {
						foreach ($name as $key => $value) {
							unset($this->data[$index][$value]);
						}
					}
					else {
						foreach ($name as $key => $value) {
							unset($this->data[$value]);
						}				
					}
				}
				else {
					if (is_array($this->data[$index])) unset($this->data[$index][$name]);
					else unset($this->data[$name]);
				}
			}
			return $this;
		}

		public function begin() :void
		{
			$this->dbaccess->begin();
		}

		public function commit() :void
		{
			$this->dbaccess->commit();
			$this->dbaccess->unlock_tables();
		}
		
		public function insert(string $table, bool $parse=true) :int
		{
			if (isset($this->data[0]) && is_array($this->data[0])) {
				$last_id = array();
				foreach ($this->data as $row) {
					$this->dbaccess->lock_tables($table, 'WRITE');
					$last_id[] = $this->dbaccess->insert($table, $row, $parse, $this->commit, $this->log_rec);
				}
			} 
			else {
				$this->dbaccess->lock_tables($table, 'WRITE');
				$last_id = $this->dbaccess->insert($table, $this->data, $parse, $this->commit, $this->log_rec);
			}
			return $last_id;
		}

		public function update(string $table, int $id, bool $parse=true) :object
		{
			$this->dbaccess->lock_tables($table, 'WRITE');
			$this->dbaccess->update($table, $this->data, $id, $parse, $this->commit, $this->log_rec);
			return $this;
		}

		public function update_where(string $table, string $where, bool $parse=true) :object
		{
			$this->dbaccess->lock_tables($table, 'WRITE');
			$this->dbaccess->update_where($table, $this->data, $where, $parse, $this->commit, $this->log_rec);
			return $this;
		}

		public function delete_where(string $table, string $where) :object
		{
			$this->dbaccess->lock_tables($table, 'WRITE');
			$this->dbaccess->delete_where($table, $where, $this->commit, $this->log_rec);
			return $this;
		}

		public function read(string $table, $select, $where=null, $order=null, $limit=null) :object
		{
			$this->reset();
			$this->dbaccess->lock_tables($table, 'WRITE');
			$this->data = $this->dbaccess->select($table, $select, $where, $order, $limit, $this->commit);
			if (count($this->data) == 1) $this->data = $this->data[0];
			return $this;
		}

		public function read_upd(string $table, $select, $where=null, $order=null, $limit=null, $lock='FOR UPDATE') :object
		{
			$this->reset();
			$this->dbaccess->lock_tables($table, 'WRITE');
			$this->data = $this->dbaccess->select($table, $select, $where, $order, $limit, $this->commit,$lock);
			if (count($this->data) == 1) $this->data = $this->data[0];
			return $this;
		}

		public function read_share(string $table, $select, $where=null, $order=null, $limit=null, $lock='LOCK IN SHARE MODE') :object
		{
			$this->reset();
			$this->dbaccess->lock_tables($table, 'WRITE');
			$this->data = $this->dbaccess->select($table, $select, $where, $order, $limit, $this->commit, $lock);
			if (count($this->data) == 1) $this->data = $this->data[0];
			return $this;
		}

		public function exist(string $table, $wpis=null, $where=null)
		{
			$this->dbaccess->lock_tables($table, 'WRITE');
			return $this->dbaccess->exist($table, $wpis, $where, $this->commit);
		}

		public function list_tables($columns=null, $filters_yes=null, $filters_no=null)
		{
			return $this->dbaccess->list_tables($columns, $filters_yes, $filters_no, $this->commit);
		}
		
		public function exist_x_id($columns=null, $id=null, $filters_yes=null, $filters_no=null) 
		{	
			if ($columns === null || !is_array($columns) || $id === null) exit ('Bad parametres in execution, check parametres.');
			foreach ($columns as $key => $column) {
				$tables = array();
				$tables = $this->dbaccess->list_tables(array($column), $filters_yes, $filters_no, $this->commit);
				$result = false;
				if (!empty($tables)) {
					foreach ($tables as $key => $table) {
						if (is_array($id)) {
							foreach ($id as $key => $value) {
								$result = $this->exist($table, array($column => $value), null, $this->commit);
								if ($result === true) break;
							}
						}
						else $result = $this->exist($table, array($column => $id), null, $this->commit);
						if ($result === true) break;
					}
				}
				if ($result === true) break;
			}

			return $result;
		}

		public function parse_in($string, $tags=true) 
		{
			return $this->dbaccess->parse_in($string, $tags);
		}

		public function __set($name, $value) 
		{
            		if (!$this->data[1] && array_key_exists($name, $this->data)) {
                		$this->data[$name] = $value;
            		}
        	}

		public function __get($name) 
		{
            		if (!$this->data[1] && array_key_exists($name, $this->data)) {
                		return $this->data[$name];
            		}
        	}
	}

?>
