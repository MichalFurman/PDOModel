# PDO Model
PDO access class with PDO model.

## Simply Usage
- First set config for MySQL database (it can be simple array):

```php
  $config = array();
  $config['DB_CONNECTION']  = 'mysql';
  $config['DB_HOST']        = 'localhost';
  $config['DB_PORT']        = '3306';
  $config['DB_DATABASE']    = 'testdb';
  $config['DB_USERNAME']    = 'access';
  $config['DB_PASSWORD']    = 'password';
```
- Then initialize singletone instance of PDOAccess by execute static method GET:

```php
   use \mfurman\pdomodel\PDOAccess;
   $dbConnect = PDOAccess::get($config);
```
- At this moment You have one instance of PDO Access class in Your app, You can get whis instance whenever You want.

- Now You can create simply model by extending dataDb class (this class has state of specific model):

```php
    use \mfurman\pdomodel\PDOAccess;
    use \mfurman\pdomodel\dataDb;

    class User extends dataDb 
    {
        private $users_table = 'users';
        private $commit;      

        public function __construct($commit=true) 
        {         
            $this->commit = $commit;  
            parent::__construct(PDOAccess::get(), $commit, false);
        }    
    
        public function getAll() :array
        {
            return $this->read($this->users_table,'*')->get();
        }

        public function getById(int $id) :array
        {
            return $this->read($this->users_table,'*','id = '.$id)->get();
        }

        public function getByName(string $name) 
        {
            return $this->read($this->users_table,'*','user_name = "'.$name.'"')->get();
        }

        public function add(array $data) :int
        {
            $this->set(array('user_name'=>$request['user_name']));        
            return $this->insert($this->users_table);
        }

        public function updateOne(array $data, int $id) :bool
        {
            if (empty($this->read($this->users_table,'*','id = '.$id)->get())) return false;
            $this->set(array('user_name'=>$request['user_name']));
            $this->update($this->users_table, $id);
            return true;
        }  

        public function deleteById(int $id) :bool
        {
            if (empty($this->read($this->users_table,'*','id = '.$id)->get())) return false;
            $this->delete_where($this->users_table, 'id = '.$id);
            return true;
        }
    }
```
... and etc.






