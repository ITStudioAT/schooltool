# AdminNavigationService Tests

## Overview

Comprehensive Pest test suite for the `App\Services\AdminNavigationService` class.

## Test File Location

-   **File**: `tests/Unit/AdminNavigationServiceTest.php`
-   **Service**: `app/Services/AdminNavigationService.php`

## Test Coverage

### 1. dashboardMenu() Tests

Tests the main dashboard navigation menu generation:

-   ✅ **Unauthenticated Users**: Returns empty array when user is not logged in
-   ✅ **Basic Menu Items**: Verifies Home, Profile, and Logout items for authenticated users
-   ✅ **Super Admin Menu**: Shows Super-Admin item for users with `super_admin` role
-   ✅ **Register System Menu**: Shows Anmeldetool for `admin` and `register_admin` roles
-   ✅ **Name Truncation**: Ensures user names are truncated to 17 characters max
-   ✅ **Multiple Roles**: Correctly displays all menu items for users with multiple roles
-   ✅ **Menu Order**: Validates Home is first and Logout is last
-   ✅ **Menu Structure**: Validates all menu items have correct keys and values

### 2. profileMenu() Tests

Tests the user profile menu generation:

-   ✅ **Role Authorization**: Returns menu for `admin`, `user`, `register_user`, and `register_admin` roles
-   ✅ **Menu Items**: Validates presence of Home, Password Change, and 2FA items
-   ✅ **Item Properties**: Checks icons, colors, actions, and routes
-   ✅ **Empty Responses**: Returns empty array for unauthorized users
-   ✅ **Consistency**: Validates all items have empty titles and secondary colors

### 3. userMenu() Tests

Tests the user management menu:

-   ✅ **Basic Menu**: Returns Home item for all authenticated users
-   ✅ **Super Admin Features**: Shows Roles menu item only for `super_admin` role
-   ✅ **Menu Structure**: Validates correct structure of menu items
-   ✅ **Role Restrictions**: Ensures non-super-admins don't see restricted items

### 4. userSelection() Tests

Tests the user selection display:

-   ✅ **Admin Access**: Returns user selection for `admin` role
-   ✅ **Restricted Access**: Returns empty array for non-admin users
-   ✅ **Data Structure**: Validates correct structure with UserService data
-   ✅ **Service Integration**: Tests integration with UserService

### 5. HasRoleTrait Integration Tests

Tests the role checking functionality:

-   ✅ **Multiple Roles**: Validates proper checking of multiple roles
-   ✅ **Graceful Handling**: Ensures unauthenticated users are handled correctly

### 6. Menu Item Consistency Tests

Validates overall menu consistency:

-   ✅ **Required Keys**: All menu items have required keys
-   ✅ **Icon Consistency**: All icons use the `mdi-` prefix convention

## Running the Tests

### Run All Tests

```bash
php artisan test
```

### Run Only AdminNavigationService Tests

```bash
php artisan test --filter=AdminNavigationServiceTest
```

### Run with Coverage

```bash
php artisan test --coverage
```

### Run Specific Test Group

```bash
php artisan test --filter=dashboardMenu
php artisan test --filter=profileMenu
php artisan test --filter=userMenu
php artisan test --filter=userSelection
```

## Test Structure

The tests use Pest's modern syntax with:

-   `describe()` blocks for grouping related tests
-   `it()` for individual test cases
-   `beforeEach()` for test setup
-   `expect()` for assertions
-   `RefreshDatabase` trait for database isolation

## Mocking

The tests mock:

-   **Auth Facade**: For authentication state
-   **User Model**: Using Laravel factories
-   **Spatie Roles**: For permission testing
-   **UserService**: For dependency injection testing

## Dependencies

Required packages (already in composer.json):

-   `pestphp/pest`: ^4.1
-   `pestphp/pest-plugin-laravel`: ^4.0
-   `mockery/mockery`: ^1.6
-   `spatie/laravel-permission`: (implied by Role model usage)

## Test Database

Tests use SQLite in-memory database (configured in `phpunit.xml`):

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

## Best Practices Implemented

1. **Test Isolation**: Each test is independent using `RefreshDatabase`
2. **Clear Naming**: Descriptive test names following "it does something" pattern
3. **Comprehensive Coverage**: Tests cover all public methods and edge cases
4. **Role-Based Testing**: Validates all role combinations
5. **Mock Usage**: Proper mocking of facades and services
6. **Assertion Clarity**: Uses Pest's fluent expectations for readability
7. **Test Organization**: Logical grouping with describe blocks

## Future Enhancements

Consider adding:

-   Integration tests with actual database
-   Tests for error scenarios and exceptions
-   Performance tests for large user sets
-   Tests for edge cases with special characters in names
-   Browser tests for complete UI flow

## Notes

-   The service uses the `HasRoleTrait` which automatically grants super_admin access to all role checks
-   User names are always truncated to 17 characters in the dashboard menu
-   The tests ensure menu structure consistency across all menu types
-   Authentication state is properly mocked to avoid actual login requirements
