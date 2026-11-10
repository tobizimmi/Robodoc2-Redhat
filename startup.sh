#!/bin/bash
# Azure App Service startup script for PHP on Linux (nginx)

# Set document root to public/
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

# Create uploads directory
mkdir -p /home/site/wwwroot/uploads
chmod 777 /home/site/wwwroot/uploads

# Restart nginx
service nginx reload || nginx -s reload
