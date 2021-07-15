<?php
	/**
	* 	Interfejs / kontrakt klasy komunikacji z bazą danych
	*/

    namespace mfurman\pdomodel;

	interface idbAccess
	{
		function __construct($connect_details);

		public function log_rec ($sql_string, $log_table);

		public function lock_tables ($db_tab_array, $type="WRITE");
		public function unlock_tables ();

		
		public function select ($db_tab, $select, $where=null , $order=null, $limit=null, $commit=true, $lock= null);
		public function insert($db_tab, $wpis, $parse=true, $commit=true, $log_rec=true);
		public function update($db_tab, $wpis, $id, $parse=true, $commit=true, $log_rec=true);
		public function update_where($db_tab, $wpis, $where, $parse=true, $commit=true, $log_rec=true);
		public function delete_where($db_tab, $where, $commit=true, $log_rec=true);
		
		public function exist($db_tab, array $wpis, $where=null, $commit=true);
		public function count($db_tab, array $wpis, $where=null, $commit=true);
		public function flat_array($array, $columns=null);
	
	        public function array_column(array $input, $columnKey, $indexKey = null);
		public function list_tables($columns=null, $filtres_yes=null, $filtres_no=null, $commit=true);
		public function list_columns($table=null, $props=null, $commit=true);
		public function exist_columns($table=null, $columns=null, $props=null, $commit=true);

	}

?>
