# Deployment

This guide covers deploying the MiniCiv application to production.

## Prerequisites

- Web server (Apache/Nginx)
- PHP 8.2+ with required extensions
- Database server
- SSL certificate

## Steps

### 1. Server Setup

- Install PHP, Composer, Node.js
- Configure web server
- Set up database

### 2. Code Deployment

Clone or upload code to server:

```bash
git clone https://github.com/your-repo/miniciv.git /var/www/miniciv
cd /var/www/miniciv
```

### 3. Dependencies

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

### 4. Environment

Copy and configure `.env`:

```bash
cp .env.example .env
# Edit .env with production values
php artisan key:generate
```

### 5. Database

```bash
php artisan migrate --force
php artisan db:seed --force
```

### 6. Permissions

Set correct permissions:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 7. Caching

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 8. Web Server Config

#### Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/miniciv/public_html;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

#### Apache

Ensure `mod_rewrite` is enabled and `.htaccess` is in `public_html`.

### 9. SSL

Configure SSL certificate (Let's Encrypt recommended).

### 10. Queue Worker (if using queues)

```bash
php artisan queue:work
```

Or use supervisor for production.

## Maintenance

- Run migrations on updates: `php artisan migrate`
- Clear caches: `php artisan cache:clear`
- Monitor logs in `storage/logs/`

## Backup

- Database: Use mysqldump or similar
- Files: Backup `storage/` and config files