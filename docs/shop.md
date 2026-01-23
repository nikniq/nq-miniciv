# Shop

The shop feature allows users to browse and purchase products. It integrates with the license system to automatically generate licenses upon successful payment.

## Configuration

To enable the shop, set the following in your `.env` file:

```env
SHOP_ENABLED=true
APP_LICENSES_ENABLED=true
APP_LICENSES_PURCHASE_ENABLED=true
```

## Shop Pages

### Shop Index (`/shop`)

Displays all available products in a grid layout.

- Shows product name, description, price, duration
- Links to individual product pages
- Requires authentication for purchases

### Product Details (`/shop/products/{product}`)

Shows detailed product information:

- Full description
- Pricing per seat
- Duration options
- Purchase form

## Purchase Flow

1. User selects product and number of seats
2. Optional: Enter domain restriction
3. Choose payment method (PayPal/Stripe)
4. Complete payment
5. License is generated and assigned to user
6. Success confirmation with license details

## Payment Integration

### PayPal

- Uses PayPal REST API
- Supports one-time payments
- Configurable currency

### Stripe

- Uses Stripe Checkout
- Secure payment processing
- Webhook handling for confirmations

## Configuration Options

### Shop Config (`config/shop.php`)

```php
return [
    'enabled' => env('SHOP_ENABLED', true),
    // Additional shop settings
];
```

### Payment Config

See `config/paypal.php` and `config/stripe.php` for payment settings.

## Admin Management

Products are managed through the admin panel:

- Add/edit products
- Set pricing and duration
- Manage categories

## User Experience

- Responsive design
- Clear pricing display
- Secure checkout process
- Email notifications (if configured)
- License delivery in dashboard

## API Integration

The shop uses internal APIs for:

- Product listing
- License creation
- Payment processing

## Troubleshooting

- Ensure `SHOP_ENABLED=true`
- Verify payment provider credentials
- Check product availability
- Monitor payment webhooks
- Review license generation logs