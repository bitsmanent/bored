Bored is a PHP micro-framework. It provides a simple framework but you can
bring things together in order to refine your own ad-hoc framework; just don't
call the bored_run() function. The main documentation is the source code.

In a nutshell:
```
<?php
include('bored.php');

bored_init();

route('GET', '/hello/!name', function($name) {
	return "Hello ${name}";
});

bored_run();
?>
```

### Concepts
Things bored will never have:

* An ORM
* A templating system
* Dependency injection
* Built-in database migration
* Unit testing
* More than 2000 SLOC.

Keep reading to learn about what bored do have.

### Routing
It works like this:

```
route('GET', '/hello/?name', function($name) {
	return "Hello ${name}";
});
```

Argument prefixes are: !mandatory and ?optional.

### Database
Database facilities are based on the mysqli PHP extension. It mainly consists
of a single function dbquery() which allows few but very convenient ways to
interact with the database. In order to enabled the database you have to define
four constants:

```
define('DBHOST', '');
define('DBUSER', '');
define('DBPASS', '');
define('DBNAME', '');
```

If one is missing, no DB connection gets opened and any call to dbquery() will
fails.  Once all of the above has been defined a connection to the DB is made
available and it's possible to use dbquery(). Keep reading.

Fetch a single row:
```
$sql = "select id,username from users where id = 30";
$user = dbquery($sql);
print_r($user);
```

This will results in the following:

```
Array
(
    [id] => 30
    [username] => userfoo
    ...
)
```

Fetch multiple rows:
```
$sql = "select id,username from users";
$users = dbquery($sql, -1); /* -1 means no limit */
print_r($users);
```

This produces an output like this:

```
Array
(
    [0] => Array
        (
            [id] => 248
            [username] => userbar
            ...
        )

    [1] => Array
        (
            [id] => 425
            [username] => userbaz
            ...
        )
    ...
)
```

### Views
...

### Session
...

### Run-time cache
...

### Utils

#### Mails
...

#### Output formatting
...
