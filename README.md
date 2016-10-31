# PHP RDBMS drivers wrapper
## What to deal with
Tired with PHP's 3 MySQL drivers? Not sure MySQLi or PDO_MySQL?

This wrapper is try to separate client code with ALL driver-specific calls without using any ORM or huge frameworks. It is also procedure-friendly.

## Features

* Generator for iteration, client code can just `foreach` the result set without using driver-specific fetch methods
* Automatic bind in-params without specifying data types (**WARNING** not appreciable if client or server is sensitive with `blob` and `string`)
* Optional facades with multiple connections

## TODO
* Generated documents
* PDO implementation
* Does not work on HHVM due to both variadic and by reference arguments are not supported

## License
MIT
