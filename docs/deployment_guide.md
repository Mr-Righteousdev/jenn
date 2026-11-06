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
# University SIS connection (read-only): the campus_dynamics schema
UNI_DB_HOST=localhost
UNI_DB_NAME=campus_dynamics
UNI_DB_USERNAME=sis_readonly_user
UNI_DB_PASSWORD=sis_readonly_password
EOF

# Set permissions
sudo chown -R www-data:www-data /var/www/jenn
sudo chmod -R 755 /var/www/jenn
sudo chmod 640 /var/www/jenn/.env

# University data: until the university's API is ready, load the academic tables from an SIS dump
# into a local read-only copy (HR, payroll and admissions data in the dump are skipped)
php src/scripts/load_university_dump.php /secure/path/passbook.sql
# Admin account, first sync, switch on the IT programmes, draft curriculum mapping, second sync
php src/scripts/seed.php

# Keep university data fresh (nightly at 02:00)
( crontab -l 2>/dev/null; echo "0 2 * * * php /var/www/jenn/src/scripts/sync.php >> /var/log/jenn-sync.log 2>&1" ) | crontab -
```

### How university data gets in

The app never stores or edits academic records itself. Students, lecturers, course units and results
belong to the university's Student Information System (SIS), and the app reads them through one
connector, `src/utils/University.php`, over a read-only connection.

1. **Sync** (`src/utils/Sync.php`) copies the university structure (all faculties, programmes, course
   units and curricula) and the students and results of programmes switched on, into this app's
   database. Run it from **Administration → University data** or from cron.
2. **Curriculum mapping** (**Curriculum mapping**) records which skills each course unit builds.
3. **Competency profiles** (`src/utils/Competency.php`) turn marks into skill levels:
   the weighted average of a student's best marks in the units that build each skill.
   Optional lecturer practical evidence counts for 25%.
4. **Job matching** scores every profile against every job.

Students sign in with their registration number and staff with their admin-system username, checked
against the SIS portal accounts. Only the emergency admin has a local password.

**Never set `UNI_AUTH_MODE=dev` in production.** It lets any university account sign in with
`DEV_LOGIN_PASSWORD` and exists only until the university confirms how portal passwords are hashed.

**Never commit the SIS dump** (`passbook.sql` is in `.gitignore`); it contains personal data.

To connect to the real university system, point the `UNI_DB_*` settings at a read-only database user
on it, or reimplement the methods of `University.php` against the university's API.

**Upgrading an existing install:** back up, then run the migrations in `database/migrations/` in order.
They clear university-sourced data (the sync rebuilds it) and keep admins, skills, CBC levels, jobs and roles.

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

    location = / { }

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
