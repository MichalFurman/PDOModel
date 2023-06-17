<?php
    namespace mfurman\pdomodel;

	interface idataDb 
	{
		public function set_table(string $table = null) :void;
		public function get_table() :string;
		public function set_commit(bool $flag = true) :void;
		public function reset() :dataDb;
		public function is() :bool;
		public function set(mixed $names, $value = null) :dataDb;
		public function unset(string $name=null, int $index=0);
		public function get(mixed $names = null, int $index = 0) :mixed;
		public function get_flat() :array;
		public function begin() :void;
		public function commit() :void;
	}
?>