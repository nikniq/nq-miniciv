# Game Server Setup

The application includes server management for tracking and monitoring game servers. This allows admins to add, edit, and monitor the status of game servers.

## Configuration

To enable server management, set the following in your `.env` file:

```env
ADMIN_SERVERS_ENABLED=true
```

## Admin Panel

Once enabled, admins can access server management from the dashboard under "Manage servers".

### Adding a Server

1. Go to Admin > Servers
2. Click "Add Server"
3. Fill in the details:
   - **Name**: Display name for the server
   - **Hostname**: Server hostname or IP
   - **Status**: Online, Maintenance, or Offline
   - **Environment**: e.g., production, staging
   - **Notes**: Additional information
   - **Meta**: JSON metadata (optional)

### Editing Servers

- Click the edit button next to a server
- Update the fields as needed
- Save changes

### Server Status

Servers can have the following statuses:

- **Online**: Server is operational
- **Maintenance**: Server is under maintenance
- **Offline**: Server is not available

## Server Integration

For servers to automatically update their status, they can send heartbeats to the API.

### Heartbeat API

#### POST /api/servers/heartbeat

Allows servers to report their status and update `last_seen_at`.

**Headers:**
- `Content-Type: application/json`
- `Authorization: Bearer {token}` (if required)

**Request Body:**
```json
{
  "hostname": "game-server-1.example.com",
  "status": "online",
  "environment": "production",
  "meta": {
    "players": 150,
    "version": "1.2.3"
  }
}
```

**Response:**
```json
{
  "status": "updated",
  "server_id": 1
}
```

If the server doesn't exist, it can be created if `AUTO_CREATE_SERVERS=true` in config.

### Implementation Example

Servers can run a cron job or background process to send heartbeats:

```bash
# Example script
curl -X POST http://your-app.com/api/servers/heartbeat \
  -H "Content-Type: application/json" \
  -d '{
    "hostname": "'$(hostname)'",
    "status": "online",
    "meta": {"uptime": "'$(uptime)'"}
  }'
```

## Database

Servers are stored in the `servers` table:

- `id`: Primary key
- `name`: Server name
- `hostname`: Hostname/IP
- `status`: Current status
- `environment`: Environment
- `last_seen_at`: Last heartbeat timestamp
- `notes`: Admin notes
- `meta`: JSON metadata
- `created_at`, `updated_at`: Timestamps

## Monitoring

- View all servers in the admin panel
- Sort by status or last seen
- Use `last_seen_at` to detect offline servers
- Store additional metrics in `meta` field

## Security

- Protect the heartbeat endpoint with authentication
- Validate input data
- Monitor for unauthorized heartbeats

## Troubleshooting

- Ensure `ADMIN_SERVERS_ENABLED` is true
- Check API logs for heartbeat errors
- Verify server hostnames are unique
- Use the admin panel to manually update status if needed