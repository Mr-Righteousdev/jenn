# Deployment Guide — Nginx VPS

## Prerequisites

- Ubuntu 22.04+ or Debian 12+
- Root or sudo access
- A domain name (optional, IP works too)
- PHP 8.1+ with extensions: `php-fpm`, `php-mysql`, `php-curl`, `php-mbstring`, `php-xml`
- MySQL 8+ or MariaDB 10.5+
- Git

---

## 1. System Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP + extensions
sudo apt install -y nginx mysql-server git curl unzip \
    php8.3-fpm php8.3-mysql php8.3-curl php8.3-mbstring php8.3-xml php8.3-cli

# Verify
php -v
mysql --version
nginx -v
```

---

## 2. Database Setup

```bash
# Secure MySQL
sudo mysql_secure_installation

# Login to MySQL
sudo mysql -u root -p

# Inside MySQL:
CREATE DATABASE jenn_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'jenn_user'@'localhost' IDENTIFIED BY 'your_strong_password_here';
GRANT ALL PRIVILEGES ON jenn_db.* TO 'jenn_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u jenn_user -p jenn_db < /path/to/jenn/database/schema.sql
```

---

## 3. Application Setup

```bash
# Clone the project
cd /var/www
sudo git clone https://your-repo-url/jenn.git
sudo chown -R $USER:$USER jenn

# Configure environment
cd jenn
cat > .env << EOF
DB_HOST=localhost
DB_NAME=jenn_db
DB_USERNAME=jenn_user
DB_PASSWORD=your_strong_password_here
RAPIDAPI_KEY=your_rapidapi_key
EOF

# Set permissions
sudo chown -R www-data:www-data /var/www/jenn
sudo chmod -R 755 /var/www/jenn
sudo chmod 640 /var/www/jenn/.env

# Seed database (optional)
php src/scripts/seed.php
```

---

## 4. Nginx Configuration

Create `/etc/nginx/sites-available/jenn`:

```nginx
server {
    listen 80;
    server_name your-domain.com;  # or your-server-ip
    root /var/www/jenn/public;
    index index.php index.html;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        include fastcgi_params;
        fastcgi_param BASE_URL '';
    }

    location ~ /\.ht {
        deny all;
    }

    # Cache static assets
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
}
```

If you want a subpath (e.g., `your-domain.com/jenn`), use:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www;

    location /jenn {
        alias /var/www/jenn/public;
        try_files $uri $uri/ @jenn;

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/jenn/public$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_param BASE_URL '/jenn';
        }
    }

    location @jenn {
        rewrite /jenn/(.*)$ /jenn/index.php last;
    }
}
```

Enable and test:

```bash
sudo ln -s /etc/nginx/sites-available/jenn /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 5. PHP-FPM Tuning

Edit `/etc/php/8.3/fpm/pool.d/www.conf`:

```ini
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

```bash
sudo systemctl restart php8.3-fpm
```

---

## 6. Security Hardening

```bash
# Firewall
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp   # if using HTTPS
sudo ufw enable

# Disable PHP info exposure
sudo sed -i 's/expose_php = On/expose_php = Off/' /etc/php/8.3/fpm/php.ini
sudo systemctl restart php8.3-fpm

# Harden .env access
sudo chmod 600 /var/www/jenn/.env

# Remove installers if any
rm -f /var/www/jenn/install.php

# Regular updates
sudo apt update && sudo apt upgrade -y
```

---

## 7. HTTPS with Let's Encrypt (if using a domain)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
# Follow prompts — certificates auto-renew
```

---

## 8. Monitoring & Maintenance

```bash
# Check nginx logs
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log

# Check PHP-FPM status
sudo systemctl status php8.3-fpm

# Database backup (cron job)
# Add to crontab: 0 3 * * * mysqldump -u jenn_user -p'pass' jenn_db > /backups/jenn_$(date +\%Y\%m\%d).sql
```

---

## 9. Troubleshooting

| Symptom | Check |
|---------|-------|
| 502 Bad Gateway | `sudo systemctl status php8.3-fpm` — PHP-FPM may be stopped |
| 404 on all pages | Nginx `root` directive points to `public/`? |
| "No input file specified" | `SCRIPT_FILENAME` path in fastcgi_param is wrong |
| White screen | Check `sudo tail -f /var/log/nginx/error.log` for PHP errors |
| DB connection refused | MySQL running? `sudo systemctl status mysql` |
| Permission denied | `sudo chown -R www-data:www-data /var/www/jenn` |
