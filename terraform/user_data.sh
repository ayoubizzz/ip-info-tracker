#!/bin/bash
set -euxo pipefail

# Variables provided by Terraform template
REPO_URL="${repo_url}"

dnf -y update
dnf -y install nginx git php php-fpm php-mbstring php-xml php-mysqlnd php-pdo php-json unzip

# install composer
php -r "copy('https://getcomposer.org/installer','/tmp/composer-setup.php');"
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f /tmp/composer-setup.php

# Create web root
mkdir -p /var/www/tracker

if [ -n "$REPO_URL" ]; then
    # clone or pull
    if [ -d /var/www/tracker/.git ]; then
        cd /var/www/tracker && git pull || true
    else
        rm -rf /var/www/tracker/*
        git clone "$REPO_URL" /var/www/tracker || true
    fi

    cd /var/www/tracker || exit 0
    # try composer install
    if [ -f composer.json ]; then
        composer install --no-dev --optimize-autoloader || true
    fi
fi

# configure nginx for PHP
cat >/etc/nginx/conf.d/tracker.conf <<'EOF'
server {
        listen 80 default_server;
        server_name _;

        root /var/www/tracker/public;
        index index.php index.html;

        location / {
                try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
                fastcgi_pass unix:/run/php-fpm/www.sock;
                fastcgi_index index.php;
                include fastcgi_params;
                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                fastcgi_param HTTP_PROXY "";
        }
}
EOF

chown -R nginx:nginx /var/www/tracker || true

nginx -t
systemctl enable --now nginx
systemctl enable --now php-fpm
