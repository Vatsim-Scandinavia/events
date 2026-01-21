# API Endpoints

## Public Endpoints (Optional API Key)

| Method | Endpoint | Name | Description | Parameters |
|--------|----------|------|-------------|------------|
| GET | `/v1/events` | `api.v1.events` | Get all events | `upcoming` (bool, default: true), `staffing` (bool, default: false) |
| GET | `/v1/events/{id}` | `api.v1.event` | Get single event by ID | `id` (event ID) |

## Protected Read Endpoints (Required API Key)

| Method | Endpoint | Name | Description | Parameters |
|--------|----------|------|-------------|------------|
| GET | `/v1/events/{id}/staffing` | `api.v1.event.staffing` | Get event staffing by event ID | `id` (event ID) |
| GET | `/v1/staffings` | `api.v1.staffings.index` | Get all staffings | None |
| GET | `/v1/staffings/message` | `api.v1.staffings.by-message` | Get staffing by Discord message ID | `message_id` (query param, required) |
| GET | `/v1/staffings/{id}` | `api.v1.staffings.show` | Get event staffing by section ID | `id` (section/staffing ID) |

## Write-Protected Endpoints (Required Write API Key)

| Method | Endpoint | Name | Description | Parameters |
|--------|----------|------|-------------|------------|
| POST | `/v1/staffing` | `api.v1.staffing.book` | Book a position | `cid`, `discord_user_id`, `position`, `message_id`, `section` (optional) |
| DELETE | `/v1/staffing` | `api.v1.staffing.unbook` | Unbook a position | `discord_user_id`, `message_id`, `position` (optional), `section` (optional) |
| POST | `/v1/staffing/setup` | `api.v1.staffing.setup` | Setup/initialize staffing | `id` (staffing ID) |
| PATCH | `/v1/staffings/{id}/update` | `api.v1.staffings.update` | Update staffing message ID | `id` (staffing ID), `message_id` |
| POST | `/v1/staffings/{id}/reset` | `api.v1.staffings.reset` | Reset all bookings for staffing | `id` (staffing ID) |

## Authentication

- **Optional API Key**: Endpoints that work with or without authentication
- **Required API Key**: Read-only operations requiring authentication
- **Required Write API Key**: Write operations requiring elevated permissions

## Notes

- All endpoints are prefixed with `/v1/`
- Recurring events are handled automatically with next occurrence calculation
- Discord integration triggers message updates on booking changes