# API Reference

The application provides REST API endpoints for license validation and logging.

## Base URL

All API endpoints are prefixed with `/api`.

## Authentication

Most endpoints require authentication. Use Bearer tokens or session cookies.

## Endpoints

### License Validation

#### POST /api/licenses/validate

Validate a license key.

**Request Body:**
```json
{
  "key": "license-key",
  "domain": "example.com" // optional
}
```

**Response:**
```json
{
  "valid": true,
  "license": {
    "id": 1,
    "key": "license-key",
    "expires_at": "2026-01-01",
    "domains": ["example.com"]
  }
}
```

#### GET /api/license/validate/{key}

Validate a license key via URL parameter.

**Response:** Same as above.

### Event Logs

#### POST /api/logs

Receive external event logs. Requires `ADMIN_EXTERNAL_LOGS_ENABLED=true`.

**Request Body:**
```json
{
  "event": "user_action",
  "data": {...},
  "timestamp": "2026-01-01T00:00:00Z"
}
```

**Response:**
```json
{
  "status": "logged"
}
```

## Error Responses

All endpoints return JSON errors:

```json
{
  "error": "Invalid license key",
  "code": 400
}
```

## Rate Limiting

API endpoints may have rate limits. Check headers for `X-RateLimit-*`.