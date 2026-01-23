# License Server Setup

The application provides a license server for validating software licenses. This allows external applications to check license validity via API.

## Configuration

To enable license features, set the following in your `.env` file:

```env
APP_LICENSES_ENABLED=true
APP_LICENSES_PUBLIC_VALIDATION=true
```

For admin management:

```env
APP_LICENSES_ADMIN=true
```

## API Endpoints

### POST /api/licenses/validate

Validate a license key with optional domain check.

**Request Body:**
```json
{
  "key": "LICENSE-KEY-HERE",
  "domain": "example.com"  // optional
}
```

**Response (Valid):**
```json
{
  "valid": true,
  "license": {
    "id": 1,
    "key": "LICENSE-KEY-HERE",
    "product": {
      "name": "Product Name"
    },
    "expires_at": "2026-12-31T23:59:59Z",
    "domains": ["example.com"],
    "seats": 5
  }
}
```

**Response (Invalid):**
```json
{
  "valid": false,
  "error": "License not found or expired"
}
```

### GET /api/license/validate/{key}

Validate a license key via URL parameter.

**Response:** Same as POST endpoint.

## Usage Examples

### cURL

```bash
# POST validation
curl -X POST http://your-app.com/api/licenses/validate \
  -H "Content-Type: application/json" \
  -d '{"key": "ABC123", "domain": "myapp.com"}'

# GET validation
curl http://your-app.com/api/license/validate/ABC123
```

### JavaScript

```javascript
// Validate license
async function validateLicense(key, domain) {
  const response = await fetch('/api/licenses/validate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ key, domain })
  });
  return response.json();
}
```

### PHP

```php
// Validate license
function validateLicense($key, $domain = null) {
  $data = ['key' => $key];
  if ($domain) $data['domain'] = $domain;

  $ch = curl_init('http://your-app.com/api/licenses/validate');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}
```

## License Management

### Creating Licenses

Licenses can be created via the admin panel or programmatically.

**Admin Panel:**
1. Go to Admin > Licenses
2. Click "Add License"
3. Select product, user, seats, expiration
4. Add domain restrictions if needed

**API Creation:**
Licenses are typically created through the purchase flow or admin API.

### License Properties

- **Key**: Unique license identifier
- **Product**: Associated product
- **User**: License owner
- **Seats**: Number of allowed activations
- **Expires At**: Expiration date
- **Domains**: Restricted domains (optional)

## Validation Logic

The validation checks:

1. License exists
2. Not expired
3. Domain matches (if restricted)
4. User has access

## Rate Limiting

Consider implementing rate limits on validation endpoints to prevent abuse.

## Security

- Use HTTPS for all API calls
- Validate input parameters
- Log validation attempts for monitoring
- Consider API key authentication for high-volume clients

## Integration

Integrate license validation into your application:

```javascript
// Check license on app start
const license = await validateLicense('YOUR-KEY');
if (!license.valid) {
  // Handle invalid license
  showError('Invalid license');
  return;
}

// Use license data
console.log('Licensed to:', license.license.product.name);
```

## Troubleshooting

- Ensure `APP_LICENSES_ENABLED=true`
- Check license key format
- Verify expiration dates
- Confirm domain restrictions
- Review application logs for API errors