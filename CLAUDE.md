# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SchoolTool is a German-language Laravel application providing administrative tools for schools. The primary features include:
- **Registration System (Anmeldetool)**: Allows students/parents to register for school dates/events
- **Tutoring System**: Manages tutoring services
- **User Management**: Multi-role system with super-admin, admin, and regular users
- **School Administration**: Multi-school support with school-specific licensing

## Tech Stack

- **Backend**: Laravel 12.34 (PHP 8.2+)
- **Frontend**: Vue 3 with Vuetify 3, Pinia for state management, Vue Router 4
- **Build Tool**: Vite 7 with Tailwind CSS 4
- **Testing**: Pest 4 (PHPUnit wrapper)
- **Authentication**: Laravel Sanctum with optional 2FA
- **Custom Package**: `itstudioat/spa` (v0.3.3) - custom Laravel SPA framework by ITStudio.at

## Development Commands

### Setup
```bash
composer setup  # Runs: install, .env copy, key:generate, migrate, npm install, npm run build
```

### Development Server
```bash
composer dev    # Runs 3 services concurrently: artisan serve, queue:listen, vite dev
```

Individual services:
```bash
php artisan serve              # Laravel development server
php artisan queue:listen --tries=1  # Queue worker
npm run dev                    # Vite dev server (port 5173)
```

### Testing
```bash
composer test                  # Runs full Pest test suite
php artisan test               # Alternative test command
php artisan test --filter=TestName  # Run specific test
```

### Building
```bash
npm run build                  # Production build with Vite
```

### Code Quality
```bash
./vendor/bin/pint              # Laravel Pint (code formatting)
```

## Application Architecture

### Three Separate SPAs

The application consists of three independent Single Page Applications:

1. **Admin SPA** (`/admin/*`): Main administration interface with authentication
2. **Homepage SPA** (`/homepage/*`, `/`): Public-facing registration and tutoring interfaces
3. **Application SPA** (`/application/*`): Additional application features

Each SPA has its own:
- Entry point: `resources/js/apps/{admin,homepage,application}.js`
- Router: `resources/routes/{admin,homepage,application}.js`
- Vuetify config: `resources/plugins/{admin,homepage,application}.js`
- Root component: `resources/js/pages/{admin,homepage,application}/App.vue`
- CSS: `resources/css/{admin,homepage,application}.css`

### Backend Structure

Controllers are organized by area:
- `app/Http/Controllers/Admin/*`: Admin-only features
- `app/Http/Controllers/Homepage/*`: Public registration/tutoring
- `app/Http/Controllers/Spa/*`: SPA framework features (routes, install/update)
- `app/Http/Controllers/User/*`: User profile management

Services (`app/Services/*`) contain business logic and are heavily tested. Each service typically has a corresponding test in `tests/Unit/` or `tests/Feature/Services/`.

Key models:
- `User`: With Spatie roles/permissions, Sanctum auth, 2FA support
- `School`: Multi-tenancy support
- `Schoolyear`: Academic year management
- `Register`: Registration events
- `RegisterDate`: Specific dates for registrations
- `RegisterDateBooking`: User bookings for dates
- `Licence`/`SchoolLicence`: School licensing
- `SchoolTool`: New tutoring feature model

### Frontend Structure

**Stores** (`resources/js/stores/`):
- Use Pinia for state management
- `ResourceStore.js`: Factory pattern for creating standard CRUD stores
- Separate stores by SPA: `admin/*`, `homepage/*`, `application/*`
- `NotificationStore.js`: Global notification system

**Components**:
- Shared components in `resources/js/pages/components/`
- Custom components: `ItsTable`, `ItsGridBox`, `ItsMenuButton`, `ItsOverlayBox`, `ItsInfoBox`, `ItsNotification`, `FileUpload`, `SearchField`, `Pagination`
- Vuetify components are auto-imported

**Naming Convention**: German is used throughout (routes, variables, UI text)

### API Architecture

All API routes in `routes/api.php` follow `/api/{area}/{resource}` pattern:
- Protected by global and API throttling (600 req/min per user)
- Most admin routes require `auth:sanctum` middleware
- Role-based access via `api-allowed` middleware
- CSRF protection via Sanctum

Public routes:
- `/api/homepage/register/*`: Registration system
- `/api/homepage/tutoring/*`: Tutoring system

Admin routes grouped by required roles:
- `api-allowed:user,admin,register_admin`: User profile operations
- `api-allowed:admin,register_admin`: School/register management
- `api-allowed:admin`: User/role administration

### Custom SPA Package (`itstudioat/spa`)

This package provides:
- Base authentication views and routes
- Role and permission management (via Spatie)
- Email verification system
- Configuration in `config/spa.php`
- Views in vendor package: `spa::admin`, `spa::homepage`, `spa::application`

## Testing Strategy

The project uses Pest for testing with good coverage:
- **Unit tests**: Service classes (`tests/Unit/*ServiceTest.php`)
- **Feature tests**: Controllers (`tests/Feature/*ControllerTest.php`)
- **Jobs tests**: Queue jobs (`tests/Unit/*JobTest.php`)
- Test setup in `tests/Pest.php` and `tests/TestCase.php`

Note: Feature tests extend `Tests\TestCase` which provides database access.

## Configuration Notes

### Throttling
Configured in `config/spa.php`:
- Web: 200 req/min per user
- API: 600 req/min per user
- Global: 400 req/min total

### Token Management
- Email verification tokens expire after 120 minutes (configurable)
- Sanctum tokens for API authentication
- Optional 2FA with time-limited tokens

### Multi-tenancy
Users belong to a school (`school_id`) and can switch schools if they have permissions. Active school/schoolyear/register stored in user session.

## Important Development Notes

- German is the primary language for code comments, UI text, and variable names
- Windows development environment (note path separators in configs)
- Uses queues for PDF generation and email sending
- File uploads handled via `FileUploadService`
- The project excludes `vendor/itstudioat/spa/src/Facades/Spa.php` from autoloading
- Source maps enabled in production builds for debugging
- Puppeteer configured in `.puppeteerrc.cjs` for PDF generation

## Recent Development (from README)

Current work on `tutoring` branch:
- Welcome screen for users (18.11.2025)
- User management for super-admin with role selection, CRUD operations
- Extensive Pest test coverage added (v3.2.10-3.2.11)
- Registration system with user overview and cleanup features
- Optional 2FA authentication (v3.2.8)

## Database

Migrations in `database/migrations/`. Recent additions include `school_tools` table for tutoring feature.

## File Aliases

Vite configured with `@` alias pointing to `resources/js/` for cleaner imports.
