# PDO Model
PDO access class with PDO model.

## Simple Instalation

Just use composer for install new dependency:
```composer require mfurman/pdomodel```


## Simple Usage

First set config for MySQL database (it can be simple array):

```php
  $config = array();
  $config['DB_CONNECTION']  = 'mysql';
  $config['DB_HOST']        = 'localhost';
  $config['DB_PORT']        = '3306';
  $config['DB_DATABASE']    = 'testdb';
  $config['DB_USERNAME']    = 'access';
  $config['DB_PASSWORD']    = 'password';
```

Then initialize singletone instance of PDOAccess by execute static method GET:

```php
   \mfurman\pdomodel\PDOAccess::get($config);
```

At this moment You have one instance of PDO Access class in Your app, You can get whis instance whenever You want.

Now You can create simply model by extending dataDb class (this class has state of specific model):

#### Model example:
```php
    use \mfurman\pdomodel\PDOAccess;
    use \mfurman\pdomodel\dataDb;

    class User extends dataDb 
    {
        private $users_table = 'users';

        public function __construct($commit=true) 
        {         
            parent::__construct(PDOAccess::get(), $commit, false);
        }    
        
        // return associative array, table of users
        public function getAll() :array
        {
            return $this->read($this->users_table,'*')->get();
        }

        // return associative array, users data
        public function getById(int $id) :array
        {
            return $this->read($this->users_table,'*','id = '.$id)->get();
        }

        // return associative array, users data
        public function getByName(string $name) 
        {
            return $this->read($this->users_table,'*','user_name = "'.$name.'"')->get();
        }

        // return id of new row,
        public function add(array $data) :int               
        {
            $this->set(array('user_name'=>$data['user_name']));        
            return $this->insert($this->users_table);
        }

        // return true or false
        public function updateOne(array $data, int $id) :bool
        {
            if (empty($this->read($this->users_table,'*','id = '.$id)->get())) return false;
            $this->set(array('user_name'=>$data['user_name']));
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

When You created models, You can create some repositories.
In repositories You have control at database transactions and commits. 

#### Repository example:
```php
    use Myvendor\Myapp\Models\Task;
    use Myvendor\Myapp\Models\User;

    class TaskRepository 
    {
        private $task;
        private $user;
        
        (...)
        
        public function store(array $data) :array
        {
            $this->task = new Task(false);                      // - set autocommit to false (true/false)
            $this->user = new User(false);                      // - set autocommit to false (true/false)
            
            // inform PDO about start transaction
            $this->task->begin();
            
            $this->user->getByName($data['user_name']);
            if ($this->user->is()) $data['user_id'] = $this->user->get('id');
            else {
                $this->user->reset();
                $data['user_id'] = $this->user->add($data);
            }

            $id = $this->task->add($data);
            
            // inform PDO to commit ealier started transaction, if something go wrong - commit will rollback
            $this->task->commit();
            
            // this toggle change model to autocommit transactions (true/false)
            $this->task->set_commit(true);
            return $this->task->getById($id);
        }        

```


## Class dataDb has build methods, which we can use in our model or repository.

#### Public methods (can be use in model and repository):

Constructor - parametres: (PDOModel-singletone, commit, flag to register any saves to database in log table)
```php
    parent::__construct(object $dbaccess, $commit=true, $log_rec=false);
```
If You would like to register activity in log table You have to create 'general_log' table (see at the end).
If not, just set this toggle to 'false' or do't use it - it's false by default

set_commit - toggle to change auto commit - default by 'true'
```php
    $this->set_commit(bool $flag=true);
```

reset - reset state of model
```php
    $this->reset();
```

is - inform about state - is hydrated or not - return 'true/false'
```php
    $this->is();
```

set - set data to model 
```php
    $this->set('name','value');
    
    //or we can set multiple 
    $this->set(array(
        'name01' => 'value01'
        'name02' => 'value02'
        'name03' => 'value03'
        )
    );
```

get - get value by name from model
```php
    $this->get('name');
    
    //or if is multiple data table -  for eg. table of 'users'
    $this->get('name',0); // - get name of first user
```

get_flat - get array of specific data as one dimension array
```php
    $this->get_flat();
```

del - remove some value from state by name
```php
    $this->del('name');
```

begin - start transaction when autocommit is false
```php
    $this->begin();
```

commit - commit started transaction when autocommit is false
```php
    $this->commit();
```

#### Protected methods (can be use in model only):


read - read data from table, hydrate state of model - execute (commit) - return model object, can be chaining

$this->read(table, columns, where, order, limit) :object
```php
    $this->read($this->myTable, '*', 'name = "John"') :object
    
    // or
    $this->read($this->myTable, array('ID', 'name', 'surname', 'city'), 'name = "John" AND age > 23', 'DESC', 1) :object
```

insert - insert data to database table - execute (commit) - return new ID of created row
```php
    $this->insert($this->myTable) :int
```

update - update data to existing row in table - execute (commit) - return model object, can be chaining
```php
    $this->update($this->myTable, $id) :object
```

update_where - update data to existing row in table - execute (commit) - return model object, can be chaining
```php
    $this->update_where($this->myTable, 'name = "John"') :object
```

delete_where - delete data from table - execute (commit) - return model object, can be chaining
```php
    $this->delete_where($this->myTable, 'name = "John"') :object
```

exist - check if data exists in database - return true/false

$this->exist(table,['name1' =>'value1', 'name2' => 'value2', ...], where)
```php
    $this->exist($this->myTable, array('name' => 'John', 'surname' => 'Smith') :bool
    
    //or
    $this->exist($this->myTable, array('name' => 'John', 'surname' => 'Smith', 'ID != '.$id) :bool    
```

## General log table structures

General log table is using when we have set session between user and server.
It using session data to recognize existing user and his activity.
We have to set two session data for logged in user:
```php
    $_SESSION['user_id'] = 12               // set when user log in - for eg ID from users table,
    $_SESSION['user_name'] = 'John Smith'   // set when user log in - usermane from users table,
```

General log table must have these specyfic columns and name:

table name: 'general_log'

columns:
- 'date': timestampler,
- 'user_id': int - $_SESSION['user_id'] 
- 'user_name': string - $_SESSION['user_name']
- 'method': string - (INSERT, UPDATE, DELETE)
- 'value': string - (SQL string)

All data in general log will be automatically filled when flag 'log_rec' is set to 'true'.



Enjoy.

## License :old_key:

Under license (MIT, Apache etc)

MIT © Michał Furman

