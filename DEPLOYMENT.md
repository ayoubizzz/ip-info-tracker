# Deployment Guide - IP Tracker Application

## Overview
This application tracks website visitors and logs their IP addresses, geolocation data, and request information using MaxMind GeoIP database.

## Prerequisites

### Local Development (XAMPP)
- PHP 8.0+
- MySQL/MariaDB 10.5+
- Composer
- MaxMind GeoLite2-City database

### Production (AWS EC2)
- Amazon Linux 2023
- PHP 8.4+
- MariaDB 10.5+
- Nginx
- Composer

## Database Setup

### 1. Create Database
```bash
mysql -u root -p
```

```sql
CREATE DATABASE IF NOT EXISTS tracker_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tracker_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON tracker_db.* TO 'tracker_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2. Import Schema
```bash
mysql -u tracker_user -p tracker_db < sql/schema.sql
```

## Configuration

### Environment Variables (Production)

Add to `/etc/php-fpm.d/www.conf`:
```ini
; Database configuration
env[DB_HOST] = 127.0.0.1
env[DB_USER] = tracker_user
env[DB_PASSWORD] = your_secure_password
env[DB_NAME] = tracker_db
```

Restart PHP-FPM:
```bash
sudo systemctl restart php-fpm
```

### Local Configuration

The `config.php` file automatically uses environment variables in production and falls back to local defaults for XAMPP.

## Installation

### 1. Clone Repository
```bash
git clone https://github.com/ayoubizzz/ip-info-tracker.git
cd ip-info-tracker
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Download GeoIP Database
Place `GeoLite2-City.mmdb` in the `geoip/` directory.

You can download it from MaxMind:
https://dev.maxmind.com/geoip/geolite2-free-geolocation-data

### 4. Set Permissions (Production)
```bash
sudo chown -R nginx:nginx /var/www/tracker
sudo chmod -R 755 /var/www/tracker
```

## Nginx Configuration (Production)

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/tracker/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Testing

### 1. Test Locally
```bash
# Using XAMPP
http://localhost/tracker/public/
```

### 2. Test on Server
```bash
# SSH into EC2
curl http://localhost/

# Check database
mysql -u tracker_user -p tracker_db -e "SELECT * FROM visitor_logs ORDER BY visited_at DESC LIMIT 5;"
```

### 3. Verify Geolocation
External visitors should show:
- ✅ Real IP address
- ✅ Country name
- ✅ City (if available in database)
- ✅ Latitude/Longitude

Local visitors (localhost) will show:
- ✅ IP: `::1` or `127.0.0.1`
- ✅ geo_ip: `8.8.8.8` (fallback for geolocation)
- ✅ Country: United States (Google DNS location)

## Features

### ✅ IP Detection
- Detects real client IP through proxies (Cloudflare, X-Forwarded-For)
- Handles IPv4 and IPv6
- Local IP fallback for testing

### ✅ Geolocation
- Country detection
- City detection (when available)
- Latitude/Longitude coordinates
- Uses MaxMind GeoLite2 database

### ✅ Request Tracking
- User agent
- Referer
- Requested URL
- Timestamp

### ✅ Error Handling
- Graceful degradation if GeoIP fails
- Silent error logging
- Never breaks the main page

## Database Schema

```sql
visitor_logs
├── id (INT, AUTO_INCREMENT, PRIMARY KEY)
├── ip (VARCHAR(45), NOT NULL) - Actual client IP
├── geo_ip (VARCHAR(45)) - IP used for geolocation
├── country (VARCHAR(100)) - Country name
├── city (VARCHAR(100)) - City name
├── latitude (DECIMAL(10,8)) - GPS latitude
├── longitude (DECIMAL(11,8)) - GPS longitude
├── user_agent (TEXT) - Browser user agent
├── referer (TEXT) - HTTP referer
├── url (TEXT) - Requested URL
└── visited_at (TIMESTAMP) - Visit timestamp
```

## Troubleshooting

### No data being inserted?
1. Check PHP-FPM error log: `sudo tail -f /var/log/php-fpm/error.log`
2. Check nginx error log: `sudo tail -f /var/log/nginx/error.log`
3. Verify database connection: Create `public/test_db.php` with config test
4. Check environment variables are set in PHP-FPM

### City shows NULL?
This is normal! MaxMind GeoLite2 has limited city coverage for:
- Residential IPs
- Some regions
- Mobile networks

The paid GeoIP2 Precision API has better coverage.

### Local testing not working?
Make sure you're using the fallback IP (`8.8.8.8`) for geolocation while storing the real localhost IP.

## Production Checklist

- [ ] Database created with secure password
- [ ] Environment variables set in PHP-FPM
- [ ] GeoLite2-City.mmdb file present
- [ ] Composer dependencies installed
- [ ] File permissions set correctly
- [ ] Nginx configuration updated
- [ ] PHP-FPM and Nginx restarted
- [ ] Test from external IP
- [ ] Verify data in database

## Security Notes

1. **Never commit** `config.php` with hardcoded credentials
2. **Always use** environment variables in production
3. **Rotate** database passwords regularly
4. **Update** GeoIP database monthly
5. **Monitor** for unusual traffic patterns

## Support

For issues or questions:
- GitHub: https://github.com/ayoubizzz/ip-info-tracker
- Documentation: See README.md

## License

[Your License Here]
