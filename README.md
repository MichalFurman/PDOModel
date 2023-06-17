# PDO Model
PDO access class with PDO model - version 1.0.3

## Changes from version 1.0.3
- minor corrections in code to version 1.0.2,
- minor corrections in interfaces for exists classes,
- added simply logger method,
- added check for PDO singleton instance,
- alignment for variable names,

## Changes from version 1.0.2
- changed database table declaration in model - now You have to declare table in constructor as a parent property after PDOAccess instance,
- changed execution protected methods in model - now You don't have to set name of table as parameter in execute method - table is declared by constructor,
- added two public methods - "set_table" and "get_table" - methods are setter and getter for change declared name of table in constructor during model life,
- changed way of execution public method "exist" - now You can set a table for this method as the last calling parameter, if You don't declare this parameter method will execute on exist declared table by constructor,

## Simple Instalation

Just use composer for install new dependency:
```composer require mfurman/pdomodel "1.0.3"```


## Simple Usage

First set config for MySQL database (it can be simple array):

```php
  $config = [
    'DB_CONNECTION'  => 'mysql',
    'DB_HOST'        => 'localhost',
    'DB_PORT'        => '3306',
    'DB_DATABASE'    => 'testdb',
    'DB_USERNAME'    => 'access',
    'DB_PASSWORD'    => 'password'
  ];
```

Then initialize singletone instance of PDOAccess by execute static method GET:

```php
   \mfurman\pdomodel\PDOAccess::get($config);
```

At this moment You have one instance of PDO Access class in Your app, You can get this instance whenever You want.

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
            parent::__construct(PDOAccess::get(), $this->users_table, $commit, false);
        }    
        
        // return associative array, table of users
        public function getAll() :array
        {
            return $this->read('*')->get();
        }

        // return associative array, users data
        public function getById(int $id) :array
        {
            return $this->read('*','id = '.$id)->get();
        }

        // return associative array, users data
        public function getByName(string $name) 
        {
            return $this->read('*','user_name = "'.$name.'"')->get();
        }

        // return id of new row,
        public function add(array $data) :int               
        {
            $this->set(array('user_name'=>$data['user_name']));        
            return $this->insert();
        }

        // return true or false
        public function updateOne(array $data, int $id) :bool
        {
            if (empty($this->read('*','id = '.$id)->get())) return false;
            $this->set(array('user_name'=>$data['user_name']));
            $this->update($id);
            return true;
        }  

        public function deleteById(int $id) :bool
        {
            if (empty($this->read('*','id = '.$id)->get())) return false;
            $this->delete_where('id = '.$id);
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


## Class dataDb has builded methods, which we can use in our model or repository.


### Public methods (can be use in model and repository):

Constructor - parametres: (PDOModel-singletone, database_table, commit, flag to register any saves to database in log table)
```php
    parent::__construct(object $dbaccess, $this->myTable, $commit=true, $log_rec=false);
```
If You would like to register activity in log table You have to create 'general_log' table (look at the end).
If not, just set this toggle to 'false' or do't use it - it's false by default

set_commit - toggle to change auto commit - default by 'true'
```php
    $this->set_commit(bool $flag=true);
```

reset - reset state of model, it doesn't reset table name
```php
    $this->reset();
```
set_table - change or set database table for model (new method)
```php
    $this->set_table(string $table);
```

get_table - return actual database table name set in model (new method)
```php
    $this->get_table();
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


### Protected methods (can be use in model only):

read - read data from table, hydrate state of model - execute (commit) - return model object, can be chaining

$this->read(table, columns, where, order, limit) :object
```php
    $this->read('*', 'name = "John"') :object
    
    // or
    $this->read(array('ID', 'name', 'surname', 'city'), 'name = "John" AND age > 23', 'DESC', 1) :object
```

insert - insert data to database table - execute (commit) - return new ID of created row
```php
    $this->insert() :int
```

update - update data to existing row in table - execute (commit) - return model object, can be chaining
```php
    $this->update($id) :object
```

update_where - update data to existing row in table - execute (commit) - return model object, can be chaining
```php
    $this->update_where('name = "John"') :object
```

delete_where - delete data from table - execute (commit) - return model object, can be chaining
```php
    $this->delete_where('name = "John"') :object
```

exist - check if data exists in database - return true/false

$this->exist(table,['name1' =>'value1', 'name2' => 'value2', ...], where, table (optional))
```php
    $this->exist(array('name' => 'John', 'surname' => 'Smith') :bool
    
    //or
    $this->exist(array('name' => 'John', 'surname' => 'Smith', 'ID != '.$id, $this->otherTable) :bool    
```

## General log table structures

General log table is used when we have set session between user and server.
It using session data to recognize existing user and his activity.
We have to set two session data for logged user:
```php
    $_SESSION['user_id'] = 12               // set when user log in - for eg ID from users table,
    $_SESSION['user_name'] = 'John Smith'   // set when user log in - username from users table,
```

General log table must have these specyfic columns and name:

table name: 'general_log'

columns:
- 'date': timestampler,
- 'user_id': int - $_SESSION['user_id'] 
- 'user_name': string - $_SESSION['user_name']
- 'method': string - (INSERT, UPDATE, DELETE - methods that executed on database)
- 'value': string - (SQL query that the user generated)

All data in general log will be automatically filled when flag 'log_rec' is set to 'true'.



Enjoy.

## License :old_key:

Under license (MIT, Apache etc)

MIT © Michał Furman

