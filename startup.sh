#!/bin/bash
# Azure App Service startup script
# Set document root to public/

# Update Apache config to use public/ as document root
if [ -f /etc/apache2/sites-available/000-default.conf ]; then
    sed -i 's|DocumentRoot /home/site/wwwroot|DocumentRoot /home/site/wwwroot/public|g' /etc/apache2/sites-available/000-default.conf
    sed -i 's|<Directory /home/site/wwwroot>|<Directory /home/site/wwwroot/public>|g' /etc/apache2/sites-available/000-default.conf
    service apache2 restart
fi

# Create uploads directory if it doesn't exist
mkdir -p /home/site/wwwroot/uploads
chmod 777 /home/site/wwwroot/uploads
