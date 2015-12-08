Bored is a PHP micro-framework. It provides a simple framework but does not
enforce any particular structure so you can bring things together in order to
refine your own ad-hoc framework. The main documentation is the source code.

In a nutshell:
```
<?php
include('bored.php');

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
interact with the database. Some example:

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

This produces a output like this:

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

### Web server configuration
I can't cover all scenarious, here is the most common where you a bored
instance for a single domain:

#### nginx

```
server {
        root /usr/share/nginx/www/bored/app/public/;
        index index.html index.php;
        server_name bored;

        location / {
                try_files $uri $uri/ /index.php?$args;
                location ~ \.php$ {
			fastcgi_pass unix:/var/run/php5-fpm.sock;
                        fastcgi_param SCRIPT_FILENAME $request_filename;
                        include fastcgi_params;
                }
        }
}
```
