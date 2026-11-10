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

echo "Startup complete — pdo_mysql: $(php -m | grep pdo_mysql)"
