# Configuration

The application is configured via the `.env` file and various config files in `config/`.

## Environment Variables

### Application

- `APP_NAME`: Application name
- `APP_ENV`: Environment (local, production)
- `APP_KEY`: Application key (generated)
- `APP_DEBUG`: Debug mode
- `APP_URL`: Base URL

### Database

- `DB_CONNECTION`: Database driver (mysql, sqlite, etc.)
- `DB_HOST`: Database host
- `DB_PORT`: Database port
- `DB_DATABASE`: Database name
- `DB_USERNAME`: Database username
- `DB_PASSWORD`: Database password

### Mail

- `MAIL_MAILER`: Mail driver (smtp, mailgun, etc.)
- `MAIL_HOST`: SMTP host
- `MAIL_PORT`: SMTP port
- `MAIL_USERNAME`: SMTP username
- `MAIL_PASSWORD`: SMTP password
- `MAIL_ENCRYPTION`: Encryption (tls, ssl)
- `MAIL_FROM_ADDRESS`: From email address

### Payments

#### PayPal

- `PAYPAL_CLIENT_ID`: PayPal client ID
- `PAYPAL_CLIENT_SECRET`: PayPal client secret
- `PAYPAL_MODE`: sandbox or live
- `PAYPAL_CURRENCY`: Currency (USD)

#### Stripe

- `STRIPE_PUBLIC_KEY`: Stripe public key
- `STRIPE_SECRET`: Stripe secret key
- `STRIPE_CURRENCY`: Currency (USD)

### Features

- `FRONTPAGE_THEME`: Frontpage theme (default, games, license, login)
- `APP_LICENSES_ENABLED`: Enable license features (true/false)
- `APP_LICENSES_PURCHASE_ENABLED`: Enable purchases (true/false)
- `APP_GAMES_ENABLED`: Enable games feature (default: false)
- `APP_MINICIV_ENABLED`: Enable MiniCiv game feature (true/false)
- `SHOP_ENABLED`: Enable shop (true/false)
- `APILAB_ENABLED`: Enable API lab (true/false)

### Admin

- `APP_LICENSES_ADMIN`: Enable admin license management (true/false)
- `APP_PRODUCT_ENABLED`: Enable admin product management (default: false)
- `ADMIN_SERVERS_ENABLED`: Enable server management (true/false)
- `ADMIN_EXTERNAL_LOGS_ENABLED`: Enable external logs (true/false)

## Config Files

- `config/app.php`: General app config
- `config/auth.php`: Authentication config
- `config/database.php`: Database config
- `config/mail.php`: Mail config
- `config/license.php`: License feature toggles
- `config/games.php`: Games config
- `config/miniciv.php`: MiniCiv game config
- `config/products.php`: Products config
- `config/shop.php`: Shop config
- `config/paypal.php`: PayPal config
- `config/stripe.php`: Stripe config
- `config/admin.php`: Admin config
- `config/frontpage.php`: Frontpage theme config

## Caching Config

After changes, cache the config:

```bash
php artisan config:cache
```

Clear cache:

```bash
php artisan config:clear
```