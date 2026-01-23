# Installation

This guide will help you set up the MiniCiv application on your local machine.

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL or another supported database
- Git

## Steps

### 1. Clone the Repository

```bash
git clone https://github.com/your-repo/miniciv.git
cd miniciv
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node Dependencies

```bash
npm install
```

### 4. Environment Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` to configure your database, mail, payment providers, etc.

### 5. Generate Application Key

```bash
php artisan key:generate
```

### 6. Database Setup

Create a database and update the `.env` file with connection details.

Run migrations:

```bash
php artisan migrate
```

Seed the database with sample data:

```bash
php artisan db:seed
```

### 7. Build Assets

```bash
npm run build
```

### 8. Serve the Application

```bash
php artisan serve
```

Visit `http://localhost:8000` in your browser.

## Additional Setup

- Configure mail settings for email functionality
- Set up payment providers (PayPal, Stripe) if using purchase features
- Configure external services as needed

## Troubleshooting

- Ensure PHP extensions are installed (e.g., pdo_mysql)
- Check file permissions for storage directories
- Run `php artisan config:cache` after changes