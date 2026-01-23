# MiniCiv Documentation

Welcome to the MiniCiv project documentation. This is a Laravel 11 application that provides a secure login portal, dashboard, license management, and game features.

## Table of Contents

- [Installation](installation.md)
- [Configuration](configuration.md)
- [Features](features.md)
- [API Reference](api.md)
- [Log Server Setup](log-server.md)
- [Game Server Setup](game-server.md)
- [Database](database.md)
- [Deployment](deployment.md)

## Overview

MiniCiv is built with Laravel 11 and includes:

- User authentication with email/password
- Session-backed dashboard
- License management and validation
- Product and purchase system
- Game listings and favorites
- Admin panel for managing users, licenses, and products
- API for license validation
- Multiple frontpage themes

## Quick Start

1. Clone the repository
2. Install dependencies: `composer install` and `npm install`
3. Copy `.env.example` to `.env` and configure
4. Run migrations: `php artisan migrate`
5. Seed data: `php artisan db:seed`
6. Build assets: `npm run build`
7. Serve: `php artisan serve`

For detailed instructions, see [Installation](installation.md).