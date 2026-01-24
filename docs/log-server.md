# Log Server Setup

The application includes a log server for ingesting external event logs via API. This allows external systems to send logs to be stored in the database.

## Configuration

To enable the log server, set the following in your `.env` file:

```env
APP_EXTLOGS_ENABLED=true
# legacy: ADMIN_EXTERNAL_LOGS_ENABLED=true is still supported as a fallback
```

## API Endpoint

### POST /api/logs

Sends an event log to the server.

**Headers:**
- `Content-Type: application/json`
- `Authorization: Bearer {token}` (if authentication is required)

**Request Body:**
```json
{
  "event": "user_login",
  "data": {
    "user_id": 123,
    "ip": "192.168.1.1",
    "user_agent": "Mozilla/5.0..."
  },
  "timestamp": "2026-01-23T12:00:00Z",
  "source": "external_app"
}
```

**Parameters:**
- `event` (string, required): Event name
- `data` (object, optional): Additional event data
- `timestamp` (string, optional): ISO 8601 timestamp. Defaults to current time
- `source` (string, optional): Source identifier

**Response:**
```json
{
  "status": "logged",
  "id": 123
}
```

**Error Response:**
```json
{
  "error": "Invalid data",
  "code": 400
}
```

## Usage Examples

### cURL

```bash
curl -X POST http://your-app.com/api/logs \
  -H "Content-Type: application/json" \
  -d '{
    "event": "user_action",
    "data": {"action": "click", "page": "dashboard"},
    "source": "web_app"
  }'
```

### JavaScript (Fetch)

```javascript
fetch('/api/logs', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    event: 'page_view',
    data: { page: '/dashboard' },
    source: 'frontend'
  })
});
```

### Python (requests)

```python
import requests
import json

data = {
  "event": "error",
  "data": {"message": "Database connection failed"},
  "source": "backend"
}

response = requests.post('http://your-app.com/api/logs',
                         json=data)
print(response.json())
```

## Database Storage

Logs are stored in the `external_logs` table with the following structure:

- `id`: Auto-increment ID
- `event`: Event name
- `data`: JSON data
- `source`: Source identifier
- `created_at`: Timestamp

## Viewing Logs

Logs can be viewed in the admin panel under "Logs" > "External Logs".

## Security Considerations

- Enable authentication if the endpoint should be protected
- Rate limit the endpoint to prevent abuse
- Validate input data to ensure proper JSON structure
- Monitor log volume to avoid database bloat

## Troubleshooting

- Ensure `ADMIN_EXTERNAL_LOGS_ENABLED` is set to `true`
 - Ensure `APP_EXTLOGS_ENABLED` is set to `true` (legacy `ADMIN_EXTERNAL_LOGS_ENABLED` is supported)
- Check application logs for API errors
- Verify JSON payload is valid
- Confirm database connection is working