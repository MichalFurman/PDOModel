<?php
    namespace mfurman\pdomodel;

	interface idataDb 
	{
		public function set_commit(bool $flag=true);
		public function reset();
		public function is();
		public function set($name, $value=null);
		public function get($name=null, $index=0);
		public function get_flat ();
		public function del($name=null, $index=0);
		public function begin();
		public function commit();
	}
?>