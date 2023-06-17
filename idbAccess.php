<?php
	/**
	* 	Interface for database service provider
	*/

    namespace mfurman\pdomodel;

	interface idbAccess
	{
		function __construct($config);
		public function parse_in(string $string, bool $tags = true) :string;
		public function log_rec(string $sql_string, bool $commit = true, string $log_table = 'general_log') :void;
		public function lock_tables(mixed $db_tab_array, string $type = "WRITE");
		public function unlock_tables() :void;
		public function begin() :void;
		public function commit() :void;
		
		public function select(string $db_tab, mixed $select='*', string $where = null , string $order = null, int $limit = null, bool $commit = true, string $lock = null) :array;
		public function insert(string $db_tab, array $data, bool $parse = true, bool $commit = true, bool $log_rec = true) :int;
		public function update (string $db_tab, array $data, int $id, bool $parse = true, bool $commit = true, bool $log_rec = true) :bool;
		public function update_where (string $db_tab, array $data, string $where, bool $parse = true, bool $commit = true, bool $log_rec = true) :bool;
		public function delete_where(string $db_tab, string $where, bool $commit = true, bool $log_rec = true) :bool;
		public function exists(string $db_tab, array $data = null, string $where = null, bool $commit = true) :bool;
		public function count(string $db_tab, array $data = null, string $where = null, bool $commit = true) :int;
		public function flat_array(array $array, $columns = null) :array;
		public function array_column(array $input, $columnKey, $indexKey = null) :mixed;
		public function list_tables(array $columns=null, array $filtres_yes=null, array $filtres_no=null, bool $commit=true) :array;
		public function list_columns(string $table=null, array $props=null, bool $commit=true) :mixed;
		public function exists_columns(string $table=null, array $columns=null, mixed $props=null, bool $commit=true) :mixed;
	}
?>
