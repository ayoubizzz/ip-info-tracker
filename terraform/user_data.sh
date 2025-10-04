#!/bin/bash
set -euxo pipefail

# Log everything for debugging
exec > >(tee /var/log/user-data.log)
exec 2>&1

echo "=== Bootstrap started at $(date) ==="

# Variables from Terraform
REPO_URL="${repo_url}"
DB_NAME="${db_name}"
DB_USER="${db_user}"
DB_PASSWORD="${db_password}"
MAXMIND_LICENSE_KEY="${maxmind_license_key}"

echo "Installing packages..."
dnf -y update
dnf -y install nginx git php php-fpm php-mbstring php-xml php-mysqlnd php-pdo php-json mariadb105-server unzip

echo "Installing Composer..."
# Set HOME environment for composer
export HOME=/root
php -r "copy('https://getcomposer.org/installer','/tmp/composer-setup.php');"
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f /tmp/composer-setup.php

echo "Setting up MariaDB..."
systemctl enable mariadb
systemctl start mariadb

# Secure MariaDB and create database
mysql -u root <<-EOSQL
-- Set root password (MariaDB 10.5+ syntax)
ALTER USER 'root'@'localhost' IDENTIFIED BY '$DB_PASSWORD';

-- Remove anonymous users
DELETE FROM mysql.user WHERE User='';

-- Remove remote root access
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Remove test database
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';

-- Create application database
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create application user
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';

FLUSH PRIVILEGES;
EOSQL

echo "MariaDB setup complete"

echo "Deploying application code..."
mkdir -p /var/www/tracker

if [ -n "$REPO_URL" ]; then
  echo "Cloning from $REPO_URL..."
  rm -rf /var/www/tracker
  git clone "$REPO_URL" /var/www/tracker || {
    echo "Git clone failed, creating placeholder"
    mkdir -p /var/www/tracker/public
    echo '<?php phpinfo();' > /var/www/tracker/public/index.php
  }
else
  echo "No repo_url provided, creating placeholder"
  mkdir -p /var/www/tracker/public
  echo '<?php phpinfo();' > /var/www/tracker/public/index.php
fi

cd /var/www/tracker

# Install composer dependencies
if [ -f composer.json ]; then
  echo "Running composer install..."
  HOME=/root composer install --no-dev --optimize-autoloader || echo "Composer install failed"
fi

# Import database schema
if [ -f sql/schema.sql ]; then
  echo "Importing database schema..."
  mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < sql/schema.sql || echo "Schema import failed"
fi

# Set correct ownership and permissions
chown -R nginx:nginx /var/www/tracker
chmod -R 755 /var/www/tracker

echo "Configuring nginx..."
cat > /etc/nginx/conf.d/tracker.conf <<'EOF'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    root /var/www/tracker/public;
    index index.php index.html;

    access_log /var/log/nginx/tracker-access.log;
    error_log /var/log/nginx/tracker-error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

rm -f /etc/nginx/conf.d/default.conf

echo "Configuring PHP-FPM environment..."
cat >> /etc/php-fpm.d/www.conf <<-PHPENV

; Pass environment variables to PHP
env[DB_HOST] = 127.0.0.1
env[DB_NAME] = $DB_NAME
env[DB_USER] = $DB_USER
env[DB_PASSWORD] = $DB_PASSWORD
PHPENV

echo "Testing nginx configuration..."
nginx -t

echo "Starting services..."
systemctl enable nginx php-fpm
systemctl restart mariadb
systemctl restart php-fpm
systemctl restart nginx

echo "=== Bootstrap completed successfully at $(date) ==="
echo "Application available at: http://$(ec2-metadata --public-ipv4 2>/dev/null | cut -d' ' -f2 || echo 'INSTANCE_IP')/"
echo "Logs: /var/log/user-data.log, /var/log/nginx/tracker-error.log"