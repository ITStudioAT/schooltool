# NavigationController Tests

## Overview
Professional Pest test suite for the `NavigationController` that handles menu generation for the admin interface. The controller provides endpoints for profile and user menu navigation with role-based access control.

## Test Coverage

### Profile Menu Tests (✅ 8 tests)
- **profile menu returns menu for admin**: Tests menu generation for admin users
- **profile menu returns menu for register admin**: Validates register_admin access
- **profile menu denies access for register user**: Ensures 403 for register_user role
- **profile menu denies access for regular user**: Tests 403 for regular user role
- **profile menu denies access for non-admin roles**: Validates unauthorized access control
- **profile menu requires authentication**: Tests 401 for unauthenticated requests
- **profile menu contains expected items**: Verifies menu structure and content
- **profile menu items have correct structure**: Tests menu item schema

### User Menu Tests (✅ 10 tests)
- **user menu returns menu and selection for admin**: Tests complete response structure
- **user menu returns menu and selection for register admin**: Validates register_admin access
- **user menu denies access for regular user**: Ensures 403 for user role
- **user menu denies access for register user**: Tests 403 for register_user role
- **user menu requires authentication**: Tests 401 for unauthenticated requests
- **user menu contains home item**: Verifies home navigation item
- **user menu contains roles item for super admin**: Tests super_admin specific menu items
- **user menu does not contain roles item for admin**: Validates role-specific filtering
- **user menu selection contains all users for admin**: Tests selection data structure
- **user menu items have correct structure**: Validates menu item schema

### Integration Tests (✅ 9 tests)
- **profile menu and user menu work together**: Tests concurrent endpoint access
- **different roles get appropriate menu access**: Comprehensive role-based access test
- **menu responses are json formatted correctly**: Tests response headers
- **profile menu returns empty for unauthenticated user attempting to bypass**: Security test
- **user menu selection structure is correct for admin**: Tests nested data structures
- **multiple sequential requests maintain consistent responses**: Tests response consistency
- **user with long name gets truncated display name in dashboard menu**: Edge case test
- **profile menu color coding is consistent**: Tests UI consistency
- **user menu color coding is consistent**: Tests UI consistency

## Current Status
- **27 tests passing** ✅
- **115 assertions total**
- **0 tests failing** ✅

## Test Setup

### Dependencies
- Uses `RefreshDatabase` trait
- Creates School, Schoolyear via factories
- Creates five user types with different roles
- All users are confirmed and active

### Test Data
- School: "Test School" (short: TEST)
- Super Admin: superadmin@example.com (role: super_admin)
- Admin: admin@example.com (role: admin)
- Register Admin: registeradmin@example.com (role: register_admin)
- Register User: registeruser@example.com (role: register_user)
- Regular User: user@example.com (role: user)

### Roles Configuration
Tests use Spatie Permission package with five role levels:
1. **super_admin**: Automatically granted access to all endpoints (via HasRoleTrait)
2. **admin**: Full access to both profile and user menus
3. **register_admin**: Full access to both profile and user menus
4. **register_user**: No access (403)
5. **user**: No access (403)

## API Endpoints Tested

### GET /api/admin/navigation/profile_menu
Returns profile menu items for authenticated admin/register_admin users.

**Access:** admin, register_admin, super_admin (via trait)

**Response:**
```json
{
    "menu": [
        {
            "title": "",
            "subtitle": "Home",
            "icon": "mdi-home",
            "color": "secondary",
            "to": "/admin"
        },
        {
            "title": "",
            "subtitle": "Kennwort ändern",
            "icon": "mdi-form-textbox-password",
            "color": "secondary",
            "action": "wantToChangePassword"
        },
        {
            "title": "",
            "subtitle": "2-FA-Authentifizierung",
            "icon": "mdi-two-factor-authentication",
            "color": "secondary",
            "action": "wantToChange2Fa"
        }
    ]
}
```

### GET /api/admin/navigation/user_menu
Returns user management menu and selection data.

**Access:** admin, register_admin, super_admin (via trait)

**Response:**
```json
{
    "menu": [
        {
            "title": "",
            "subtitle": "Home",
            "icon": "mdi-home",
            "color": "secondary",
            "to": "/admin"
        },
        {
            "title": "",
            "subtitle": "Rollen",
            "icon": "mdi-badge-account-horizontal-outline",
            "color": "secondary",
            "to": "/admin/users/roles"
        }
    ],
    "selection": [
        {
            "title": "Alle Benutzer",
            "icon": "mdi-account-group",
            "url": "/admin/users/all_users",
            "infos": {}
        }
    ]
}
```

## Running Tests

```bash
# Run all NavigationController tests
php artisan test --filter=NavigationControllerTest

# Run specific test
php artisan test --filter="profile menu returns menu for admin"

# Run with verbose output
php artisan test --filter=NavigationControllerTest -v
```

## Test Results
✅ All 27 tests passing with 115 assertions

## Architecture Notes

### Controller Behavior
- **profileMenu()**: Returns menu items for profile actions (password change, 2FA)
- **userMenu()**: Returns menu items for user management plus selection data

### Access Control
Uses `HasRoleTrait` which automatically adds `super_admin` to allowed roles:

```php
// From HasRoleTrait
$roles[] = 'super_admin';
```

This means:
- Explicit check: `['admin', 'register_admin']`
- Actual check: `['admin', 'register_admin', 'super_admin']`

Returns 403 "Sie haben keine Berechtigung" for unauthorized access.

### Service Layer
`AdminNavigationService` provides menu generation logic:
- **profileMenu()**: Returns profile-related actions
- **userMenu()**: Returns user management navigation
- **userSelection()**: Returns informational blocks for user overview

### Menu Structure
All menu items contain:
- `title`: Main title (usually empty for icons-only display)
- `subtitle`: Descriptive text
- `icon`: Material Design Icon identifier
- `color`: UI color scheme (always "secondary")
- `to` or `action`: Navigation target or action handler

### Role-Specific Behavior
**Super Admin:**
- Sees "Rollen" menu item in user menu
- Gets access via HasRoleTrait automatic inclusion

**Admin:**
- Sees "Alle Benutzer" in selection
- Full access to both menus
- No "Rollen" menu item

**Register Admin:**
- Full access to both menus
- Same permissions as admin
- No "Rollen" menu item

**Register User & User:**
- No access to either endpoint (403)
- Must have admin or register_admin role

## Important Notes

1. **Super Admin Automatic Access**: The HasRoleTrait automatically adds super_admin to any role check, granting universal access
2. **Role-Based Menu Items**: Menu content varies by role (e.g., Rollen item only for super_admin)
3. **Selection Data**: Only returned in userMenu endpoint, contains user statistics
4. **Authentication Required**: Both endpoints require Sanctum authentication
5. **Consistent Styling**: All menu items use "secondary" color scheme
6. **German Language**: Menu items and error messages are in German

## Business Logic

### Profile Menu
Provides quick access to common profile actions:
- Home navigation
- Password change functionality
- Two-factor authentication management

### User Menu
Provides access to user management:
- Home navigation
- Role management (super admin only)
- User overview with statistics (admin/super admin)

### Selection Blocks
Informational tiles showing:
- Total user counts
- User categories
- Quick navigation to user lists

## Test Coverage Breakdown

**Profile Menu Operations**: 8 tests covering menu generation, access control, content verification, and structure validation.

**User Menu Operations**: 10 tests covering menu and selection generation, role-specific content, access control, and data structure validation.

**Integration & Edge Cases**: 9 tests covering concurrent access, role-based filtering, response consistency, security, and UI consistency.

## Security Features Tested

1. **Authentication Check**: Verifies 401 for unauthenticated requests
2. **Authorization Check**: Verifies 403 for unauthorized roles
3. **Role-Based Content**: Tests that menu items are role-appropriate
4. **Consistent Responses**: Ensures no data leakage across requests
5. **Trait Override**: Tests super_admin automatic access works correctly

## Future Enhancement Opportunities

1. Add tests for dashboard menu generation
2. Test menu item ordering and sorting
3. Add tests for dynamic menu based on school configuration
4. Test menu caching if implemented
5. Add tests for menu personalization features
6. Test internationalization if menu supports multiple languages
7. Add performance tests for large user sets in selection data
8. Test menu items for schools with multiple schoolyears
