#!/bin/bash
# Start php-fpm
php-fpm -D

# Start nginx
nginx -g "daemon off;"
