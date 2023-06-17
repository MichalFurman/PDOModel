<?php
    namespace mfurman\pdomodel;

	class dataDb implements idataDb
	{
		
		private $dbaccess;
		private $table = null;
		private $data = [];
		private $commit = true;
		private $log_rec = true;

		function __construct(object $dbaccess, string $table, bool $commit = true, bool $log_rec = true) 
		{
			if (!isset($dbaccess)) throw new \InvalidArgumentException('Missing idatabase class - please check arguments of dependency injection.');
			if (!isset($table)) throw new \InvalidArgumentException('Missing database table - please check arguments of dependency injection.');
			$this->dbaccess = $dbaccess;
			$this->table = $table;
			$this->commit = $commit;
			$this->log_rec = $log_rec;
		}

		public function set_table(string $table = null) :void
		{
			if (!isset($table)) throw new \InvalidArgumentException('Missing table parameter - please check arguments of method "set_table".');
			$this->table = $table;
		}
		
		public function get_table() :string
		{
			return $this->table;
		}

		public function set_commit(bool $flag = true) :void
		{
			$this->commit = $flag;
		}

		public function reset() :dataDb
		{
			$this->data = [];
			return $this;
		}

		public function is() :bool
		{
			
			if (empty($this->data)) return false;
			return true;
		}

		public function set(mixed $names, $value = null) :dataDb
		{
			
			if (is_array($names)) {
				foreach ($names as $key => $value) {
					$this->data[$key] = $value;
				}
			}
			else {
				$this->data[$names] = $value;
			}
			return $this;
		}

		public function get(mixed $names = null, int $index = 0) :mixed
		{		
			if ($names !== null && !is_array($names)) {
				if (isset($this->data[$index][$names])) return $this->data[$index][$names];
				else if (isset($this->data[$names])) return $this->data[$names];
				else return null;
			}
			else if ($names !== null && is_array($names)) {
				if (count($this->data) > 1) return $this->dbaccess->flat_array($this->data, $names);
				else {
					$result = array();
					foreach ($names as $key => $value) {
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

		public function get_flat() :array
		{
			return $this->dbaccess->flat_array($this->get());
		}

		public function unset($names = null, $index = 0)
		{
			if ($names === null) {
				$this->data = [];
			}
			else {
				if (is_array($names)) {
					if (isset($this->data[$index]) && is_array($this->data[$index])) {
						foreach ($names as $key => $value) {
							unset($this->data[$index][$value]);
						}
					}
					else {
						foreach ($names as $key => $value) {
							unset($this->data[$value]);
						}				
					}
				}
				else {
					if (is_array($this->data[$index])) unset($this->data[$index][$names]);
					else unset($this->data[$names]);
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
		
		protected function insert(bool $parse = true) :int
		{
			if (isset($this->data[0]) && is_array($this->data[0])) {
				$last_id = array();
				foreach ($this->data as $row) {
					$this->dbaccess->lock_tables($this->table, 'WRITE');
					$last_id[] = $this->dbaccess->insert($this->table, $row, $parse, $this->commit, $this->log_rec);
				}
			} 
			else {
				$this->dbaccess->lock_tables($this->table, 'WRITE');
				$last_id = $this->dbaccess->insert($this->table, $this->data, $parse, $this->commit, $this->log_rec);
			}
			return $last_id;
		}

		protected function update(int $id, bool $parse = true) :object
		{
			$this->dbaccess->lock_tables($this->table, 'WRITE');
			$this->dbaccess->update($this->table, $this->data, $id, $parse, $this->commit, $this->log_rec);
			return $this;
		}

		protected function update_where(string $where, bool $parse = true) :object
		{
			$this->dbaccess->lock_tables($this->table, 'WRITE');
			$this->dbaccess->update_where($this->table, $this->data, $where, $parse, $this->commit, $this->log_rec);
			return $this;
		}

		protected function delete_where(string $where) :object
		{
			$this->dbaccess->lock_tables($this->table, 'WRITE');
			$this->dbaccess->delete_where($this->table, $where, $this->commit, $this->log_rec);
			return $this;
		}

		protected function read(mixed $select, string $where = null, string $order = null, int $limit = null) :object
		{
			$this->reset();
			$this->dbaccess->lock_tables($this->table, 'WRITE');
			$this->data = $this->dbaccess->select($this->table, $select, $where, $order, $limit, $this->commit);
			if (count($this->data) == 1) $this->data = $this->data[0];
			return $this;
		}

		protected function read_upd(mixed $select, string $where = null, string $order = null, int $limit = null, string $lock = 'FOR UPDATE') :object
		{
			$this->reset();
			$this->dbaccess->lock_tables($this->table, 'WRITE');
			$this->data = $this->dbaccess->select($this->table, $select, $where, $order, $limit, $this->commit,$lock);
			if (count($this->data) == 1) $this->data = $this->data[0];
			return $this;
		}

		protected function read_share(mixed $select, string $where = null, string $order = null, int $limit = null, string $lock = 'LOCK IN SHARE MODE') :object
		{
			$this->reset();
			$this->dbaccess->lock_tables($this->table, 'WRITE');
			$this->data = $this->dbaccess->select($this->table, $select, $where, $order, $limit, $this->commit, $lock);
			if (count($this->data) == 1) $this->data = $this->data[0];
			return $this;
		}

		public function exists(array $data = null, string $where = null, string $table = null)
		{
			$table === null ? $table = $this->table : null;
			$this->dbaccess->lock_tables($table, 'WRITE');
			return $this->dbaccess->exists($table, $data, $where, $this->commit);
		}

		public function list_tables(array $columns = null, array $filters_yes = null, array $filters_no = null)
		{
			return $this->dbaccess->list_tables($columns, $filters_yes, $filters_no, $this->commit);
		}
		
		public function exists_x_id(array $columns = null, $id = null, array $filters_yes = null, array $filters_no = null) 
		{	
			if ($columns === null || !is_array($columns) || $id === null) exit ('Bad parametres in execution, check parametres.');
			foreach ($columns as $key => $column) {
				$tables = array();
				$tables = $this->dbaccess->list_tables([$column], $filters_yes, $filters_no);
				$result = false;
				if (!empty($tables)) {
					foreach ($tables as $key => $table) {
						if (is_array($id)) {
							foreach ($id as $key => $value) {
								$result = $this->dbaccess->exists($table, [$column => $value]);
								if ($result === true) break;
							}
						}
						else $result = $this->dbaccess->exists($table, [$column => $id]);
						if ($result === true) break;
					}
				}
				if ($result === true) break;
			}

			return $result;
		}

		public function parse_in(string $string, bool $tags = true) 
		{
			return $this->dbaccess->parse_in($string, $tags);
		}

		public function __set($name, $value) 
		{
			if (!isset($this->data[1])) {
				$this->data[$name] = $value;
			}
       	}

		public function __get($name) 
		{
			if (!isset($this->data[1]) && array_key_exists($name, $this->data)) {
				return $this->data[$name];
			}
		}
	}

?>
