# Database

The application uses Laravel migrations for database schema management.

## Tables

### users

- `id`: Primary key
- `name`: User's name
- `email`: Email address (unique)
- `email_verified_at`: Email verification timestamp
- `password`: Hashed password
- `is_admin`: Admin flag
- `admin_email`: Admin email
- `remember_token`: Remember token
- `created_at`, `updated_at`: Timestamps

### licenses

- `id`: Primary key
- `user_id`: Foreign key to users
- `product_id`: Foreign key to products
- `identifier`: License identifier
- `key`: License key
- `seats`: Number of seats
- `expires_at`: Expiration date
- `created_at`, `updated_at`: Timestamps

### products

- `id`: Primary key
- `name`: Product name
- `description`: Description
- `category`: Category (e.g., Game)
- `price`: Price per seat
- `duration`: Duration in days
- `created_at`, `updated_at`: Timestamps

### license_domains

- `id`: Primary key
- `license_id`: Foreign key to licenses
- `domain`: Domain name
- `created_at`, `updated_at`: Timestamps

### favourites

- `id`: Primary key
- `user_id`: Foreign key to users
- `favoritable_type`: Morph type (e.g., App\Models\Product)
- `favoritable_id`: Morph ID
- `created_at`, `updated_at`: Timestamps

### servers

- `id`: Primary key
- `name`: Server name
- `ip`: IP address
- `created_at`, `updated_at`: Timestamps

### event_logs

- `id`: Primary key
- `event`: Event name
- `data`: JSON data
- `created_at`: Timestamp

### external_logs

- `id`: Primary key
- `event`: Event name
- `data`: JSON data
- `source`: Source
- `created_at`: Timestamp

### media

- `id`: Primary key
- `name`: File name
- `path`: File path
- `mime_type`: MIME type
- `size`: File size
- `created_at`, `updated_at`: Timestamps

### mini_civ_states

- `id`: Primary key
- `user_id`: Foreign key to users
- `state`: JSON game state
- `created_at`, `updated_at`: Timestamps

### wp_posts

- `id`: Primary key
- `title`: Post title
- `content`: Post content
- `status`: Status
- `created_at`, `updated_at`: Timestamps

## Migrations

Migrations are located in `database/migrations/`. Run with:

```bash
php artisan migrate
```

## Seeders

Seeders provide sample data. Located in `database/seeders/`. Run with:

```bash
php artisan db:seed
```

## Factories

Model factories for testing. Located in `database/factories/`.