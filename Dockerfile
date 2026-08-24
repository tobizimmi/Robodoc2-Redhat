Warning: PHP Startup: Unable to load dynamic library 'zip' (tried: /usr/local/lib/php/extensions/no-debug-non-zts-20230831/zip (/usr/local/lib/php/extensions/no-debug-non-zts-20230831/zip: cannot open shared object file: No such file or directory), /usr/local/lib/php/extensions/no-debug-non-zts-20230831/zip.so (libzip.so.5: cannot open shared object file: No such file or directory)) in Unknown on line 0
Fatal error: Uncaught PDOException: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'robodoc2.quick_captures' doesn't exist in /var/www/html/app/Database.php:21
Stack trace:
#0 /var/www/html/app/Database.php(21): PDOStatement->execute(Array)
#1 /var/www/html/app/Database.php(31): Database::query('SHOW COLUMNS FR...', Array)
#2 /var/www/html/app/helpers.php(1485): Database::fetchAll('SHOW COLUMNS FR...')
#3 /var/www/html/app/bootstrap.php(7): require_once('/var/www/html/a...')
#4 /var/www/html/app/cron/runner.php(11): require_once('/var/www/html/a...')
#5 {main}
  thrown in /var/www/html/app/Database.php on line 21
