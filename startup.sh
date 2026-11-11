#!/bin/bash
# Azure App Service startup script

# Install and enable pdo_mysql
PDO_MYSQL_SO="/usr/local/lib/php/extensions/no-debug-non-zts-20230831/pdo_mysql.so"

# Build extension if not exists
if [ ! -f "$PDO_MYSQL_SO" ]; then
    docker-php-ext-install pdo_mysql 2>/dev/null || true
fi

# Create php.ini with pdo_mysql
if [ -f /usr/local/etc/php/php.ini-production ]; then
    cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
fi
echo "extension=$PDO_MYSQL_SO" >> /usr/local/etc/php/php.ini

# PHP upload limits (php.ini-production defaults to 2M/8M; app allows up to 500 MB)
cat >> /usr/local/etc/php/php.ini << 'INI'
upload_max_filesize = 512M
post_max_size = 520M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
INI

# nginx request body limit (default is 1 MB; conf.d is included in the http block)
echo "client_max_body_size 520M;" > /etc/nginx/conf.d/upload-limit.conf

# Restart PHP-FPM to pick up new extension
pkill -USR2 php-fpm 2>/dev/null || true
sleep 1

# Set nginx document root to public/
cat > /etc/nginx/conf.d/default.conf << 'NGINX'
server {
    listen 8080;
    root /home/site/wwwroot/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX

mkdir -p /home/site/wwwroot/uploads
chmod 777 /home/site/wwwroot/uploads

service nginx reload 2>/dev/null || nginx -s reload 2>/dev/null || true

date "+%F %T startup.sh ran" > /tmp/robodoc_startup_ran
echo "Startup complete — pdo_mysql: $(php -m | grep pdo_mysql)"
