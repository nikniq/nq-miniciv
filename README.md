# Mini Civilisation

Game portal for Mini Civilisation, built on Laravel.

## Layout

The web root is `public_html/` instead of Laravel's default `public/`, so the
project can be deployed to shared hosting that serves `public_html` directly.
This is wired up in two places:

- `bootstrap/app.php` calls `$app->usePublicPath(... '/public_html')`.
- `vite.config.js` sets `publicDirectory: 'public_html'` so built assets land there.

## Setup

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```
