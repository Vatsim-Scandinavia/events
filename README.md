# VATSIM Scandinavia Events System

A comprehensive Laravel + React/Inertia.js events management system with calendars, recurring events, staffing management, Discord bot integration, and Control Center API synchronization.

## Features

- **Calendars**: Admin-managed calendars with public/private visibility
- **Events**: Full CRUD with RFC 5545 recurring patterns, 16:9 banner images, Markdown descriptions
- **Staffing**: Multi-section rosters with drag-drop position ordering, Discord bot booking integration
- **Integrations**: 
  - Discord Bot API for staffing bookings (FastAPI bot compatible)
  - Control Center API for ATC position data and booking sync
  - Handover OAuth for VATSIM authentication
  - Discord webhooks for event notifications
- **Permissions**: Strict role-based access control (admin, moderator, user)
- **Dev Login**: Easy development testing without OAuth setup

## Tech Stack

- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: React 18, Inertia.js, Tailwind CSS 4
- **Database**: SQLite (default) or MySQL/PostgreSQL
- **Queue**: Database driver (switchable to Redis)
- **Storage**: Local filesystem or S3-compatible

## Quick Start

### Prerequisites

- PHP 8.2+, Composer
- Node.js 18+, npm
- SQLite/MySQL/PostgreSQL

### Installation

```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Create database (SQLite)
touch database/database.sqlite

# Run migrations and seeders
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder

# Create storage link
php artisan storage:link

# Build frontend
npm run build
# OR for development
npm run dev
```

### Start Application

```bash
# Option 1: All-in-one dev server
composer run dev

# Option 2: Manual
php artisan serve          # Terminal 1
php artisan queue:work     # Terminal 2
npm run dev                # Terminal 3
```

Visit: http://localhost:8000

## Configuration

### Required: Handover OAuth

```env
OAUTH_CLIENT_ID=your_client_id
OAUTH_CLIENT_SECRET=your_client_secret
OAUTH_BASE_URL=https://handover.vatsim-scandinavia.org
```

### Required: Discord Bot Integration

```env
DISCORD_BOT_API_URL=http://your-bot-server:80
DISCORD_BOT_API_TOKEN=your_shared_secret_token
DISCORD_BOT_TOKEN=your_discord_bot_token
DISCORD_GUILD_ID=your_discord_server_id
DISCORD_MENTION_ROLE_ID=your_role_id_for_notifications
```

### Required: Control Center API

```env
CONTROL_CENTER_API_URL=https://cc.vatsim-scandinavia.org/api
CONTROL_CENTER_API_TOKEN=your_api_token
```

### Optional: Discord Webhooks

```env
DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/your_webhook_url
```

### Optional: S3 Storage

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket
```

## First Admin User

### Option A: Development Login (Easiest)

```bash
php artisan tinker
```

```php
$admin = User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'vatsim_cid' => '1000001',
]);
$admin->assignRole('admin');
```

Visit http://localhost:8000, click "Dev Login", select your admin user, generate and click the login link.

### Option B: OAuth + Tinker

1. Login via Handover OAuth
2. Run in tinker:

```php
$user = User::where('vatsim_cid', 'YOUR_CID')->first();
$user->assignRole('admin');
```

## Authorization Model

### Roles

1. **Admin** - Full system access
   - Create/edit/delete calendars
   - Create/edit/delete events
   - Manage staffings and unbook positions
   - Manage users and roles

2. **Moderator** - Event and staffing management
   - Create/edit/delete events (cannot manage calendars)
   - Manage staffings and unbook positions
   - No user/role management

3. **User** - Basic access
   - View public calendars and events
   - Book positions through Discord bot only
   - Cannot unbook or manage content

### Key Rules

- ✅ Only **admins** can manage calendars
- ✅ **Admins and moderators** can manage events and staffings
- ✅ **Booking happens exclusively through Discord bot** (no frontend booking)
- ✅ Only **admins and moderators** can unbook positions via web interface

## Discord Bot Integration

All staffing bookings must go through your Discord bot. The system communicates with your FastAPI bot server.

### Bot Endpoints (Your Bot Must Implement)

```
POST /staffings/setup   - Create new Discord staffing message
POST /staffings/update  - Update existing Discord message
POST /staffings/{id}/reset - Reset all bookings for a staffing
```

### Laravel API for Bot

```
GET  /api/events                    - List all events
GET  /api/events/{id}/staffing      - Get event staffing data
POST /api/staffing                  - Book position (bot only)
DELETE /api/staffing                - Unbook position (bot only)
POST /api/staffings/{id}/reset      - Reset staffing (bot triggered)
```

See [API_ENDPOINTS.md](API_ENDPOINTS.md) for full documentation.

## Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=CalendarTest

# Current status
✅ 35 tests passing (61 assertions)
```

See [TESTING.md](TESTING.md) for authorization model and testing details.

## Key Features

### Recurring Events

Uses RFC 5545 rrule format:
- Weekly: `FREQ=WEEKLY;COUNT=10`
- Bi-weekly: `FREQ=WEEKLY;INTERVAL=2;COUNT=5`
- With end date: `FREQ=WEEKLY;UNTIL=20261231T235959Z`

Only recurring events can have staffing sections.

### Staffing Management

- Add multiple sections per event (e.g., "Early Shift", "Late Shift")
- Drag-and-drop position ordering
- Automatic Discord message creation and updates
- Control Center booking synchronization
- UTC timezone for all position times

### Event Banners

- 16:9 aspect ratio required
- Uploaded to storage (local or S3)
- Displayed in event listings and Discord embeds

### Pre-Event Reminders

Automatic Discord notifications sent 2 hours before event start (with role mention).

### Automatic Staffing Reset

Recurring event staffings automatically reset after each occurrence completes.

## Documentation

- **[GETTING_STARTED_DEV_LOGIN.md](GETTING_STARTED_DEV_LOGIN.md)** - Quick dev login setup
- **[API_ENDPOINTS.md](API_ENDPOINTS.md)** - Complete API documentation for bot integration
- **[TESTING.md](TESTING.md)** - Testing guide and authorization model

## Troubleshooting

### Queue Jobs Not Processing
```bash
php artisan queue:work
# Check failed_jobs table
# View logs in storage/logs/laravel.log
```

### Discord Notifications Not Sending
```bash
php artisan tinker
app(\App\Services\DiscordNotificationService::class)->testConnection()
```

### Banner Upload Fails
- Verify storage link: `php artisan storage:link`
- Check image is 16:9 ratio
- Verify `storage/` directory permissions

### Control Center Sync Issues
- Verify API URL and token in `.env`
- Check Laravel logs for API errors
- Test booking manually via tinker

## Development

### Code Style

```bash
./vendor/bin/pint
```

### Scheduled Tasks

```bash
# Run scheduler (for production)
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Tasks:
- Hourly: Reset completed event staffings
- Every 5 minutes: Send pre-event reminders

## Architecture

```
app/
├── Http/Controllers/
│   ├── Api/ApiController.php          # Bot-compatible API
│   ├── CalendarController.php         # Calendar CRUD
│   ├── EventController.php            # Event CRUD
│   ├── StaffingController.php         # Staffing management
│   └── StaffingPositionController.php # Position management & unbooking
├── Models/                             # Eloquent models
├── Services/
│   ├── ControlCenterService.php       # CC API integration
│   ├── DiscordBotNotificationService.php  # Bot communication
│   ├── DiscordChannelService.php      # Discord channel fetching
│   ├── DiscordNotificationService.php # Webhook notifications
│   └── RecurringEventService.php      # Rrule handling
├── Jobs/
│   ├── ResetStaffingForCompletedEvents.php
│   ├── SendPreEventReminders.php
│   └── UpdateDiscordStaffingMessage.php
├── Observers/
│   └── EventObserver.php              # Auto Discord notifications
└── Policies/
    ├── CalendarPolicy.php             # Admin-only calendar access
    └── EventPolicy.php                # Admin/moderator event access

resources/js/
├── Pages/                              # Inertia pages
│   ├── Calendars/
│   ├── Events/
│   └── Staffings/
├── Components/                         # React components
└── Layouts/                            # Layout wrapper
```

## License

This project is released under the GPL v3 license.

## Support

For issues and questions, contact the VATSIM Scandinavia development team.
