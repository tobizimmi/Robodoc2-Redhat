#!/bin/bash
# Azure App Service startup script

# Install pdo_mysql extension
echo "Installing pdo_mysql..."
apt-get update -qq 2>/dev/null
apt-get install -y php8.2-mysql 2>/dev/null
docker-php-ext-install pdo_mysql 2>/dev/null || true

# Try pecl if apt fails
if ! php -m | grep -q pdo_mysql; then
    pecl install pdo_mysql 2>/dev/null || true
    echo "extension=pdo_mysql.so" >> /usr/local/etc/php/conf.d/pdo_mysql.ini
fi

# Try direct enable
phpenmod mysql 2>/dev/null || true
phpenmod pdo_mysql 2>/dev/null || true

echo "PDO drivers: $(php -r 'print_r(PDO::getAvailableDrivers());')"

# Set nginx document root to public/
cat > /etc/nginx/conf.d/default.conf << 'NGINX'
server {
    listen 8080;
    listen [::]:8080;
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

echo "Startup complete"
