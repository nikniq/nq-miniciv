# API Lab

The API Lab is an interactive web interface for testing the license validation API without writing code. It allows users to experiment with API calls directly in the browser.

## Configuration

To enable the API Lab, set the following in your `.env` file:

```env
APILAB_ENABLED=true
APP_LICENSES_PUBLIC_VALIDATION=true
```

## Accessing the API Lab

The API Lab is available at `/api-lab` for authenticated users.

Navigate to: `https://your-app.com/api-lab`

## Features

### License Validation Testing

- Input field for license codes
- Real-time API calls
- JSON response display
- Success/error status indicators

### Interactive Interface

- Clean, user-friendly form
- Immediate feedback
- Formatted JSON output
- Error handling

## Usage

1. Enter a license code in the input field
2. Click "Validate license"
3. View the API response below
4. Response shows validation status and license details

## API Endpoint

The API Lab tests the `POST /api/licenses/validate` endpoint.

**Request Format:**
```json
{
  "key": "LICENSE-CODE"
}
```

**Response Format:**
```json
{
  "valid": true,
  "license": {
    "id": 1,
    "key": "LICENSE-CODE",
    "product": {"name": "Product Name"},
    "expires_at": "2026-12-31T23:59:59Z",
    "domains": [],
    "seats": 1
  }
}
```

## JavaScript Implementation

The API Lab uses vanilla JavaScript for API calls:

```javascript
const form = document.getElementById('license-test-form');
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = new FormData(form);
  const licenseCode = formData.get('license_code');

  try {
    const response = await fetch('/api/licenses/validate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ key: licenseCode })
    });

    const data = await response.json();
    displayResult(data);
  } catch (error) {
    displayError(error.message);
  }
});
```

## Integration with Shop

The API Lab includes links to:

- Browse products in the shop
- Create dashboard account
- Access other features

## Security Considerations

- Only available to authenticated users
- Rate limiting may apply
- Logs API usage for monitoring
- Validates input before API calls

## Development

The API Lab view is located at `resources/views/api/lab.blade.php`.

To modify:

- Update the form HTML
- Modify JavaScript for different endpoints
- Add additional test cases
- Enhance error handling

## Troubleshooting

- Ensure `APILAB_ENABLED=true`
- Verify user authentication
- Check `APP_LICENSES_PUBLIC_VALIDATION=true`
- Review browser console for JavaScript errors
- Test API endpoint directly with tools like Postman