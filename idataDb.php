<?php
    namespace Myvendor\Actaskman\Models;

	interface idataDb 
	{
		public function reset();
		public function set($name, $value=null);
		public function get($name=null, $index=0);
		public function del($name=null, $index=0);

		public function insert(string $table, bool $parse=true);
		public function update(string $table, int $id, bool $parse=true);
		public function update_where(string $table, string $where, bool $parse=true);
		public function delete_where(string $table, string $where);
		public function read(string $table, string $select, $where=null, $order=null, $limit=null);
	}
?>