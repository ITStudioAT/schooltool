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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.16
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- vue (VUE) - v3
- laravel-echo (ECHO) - v2
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.
- `laravel-api` — Build production-grade Laravel REST APIs using opinionated architecture patterns with Laravel best practices. Use when building, scaffoling, or reviewing Laravel APIs with specifications for stateless design, versioned endpoints, invokable controllers, Form Request DTOs, Action classes, JWT authentication, and PSR-12 code quality standards. Triggers on &quot;build a Laravel API&quot;, &quot;create Laravel endpoints&quot;, &quot;add API authentication&quot;, &quot;review Laravel API code&quot;, &quot;refactor Laravel API&quot;, or &quot;improve Laravel code quality&quot;.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

</laravel-boost-guidelines>
