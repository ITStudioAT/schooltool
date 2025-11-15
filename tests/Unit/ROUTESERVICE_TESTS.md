# RouteService Tests

## Overview
Professional Pest test suite for `App\Services\RouteService.php` covering authorization and route matching for both web and API routes with role-based access control using Spatie Permission package.

## Test Coverage

### Total Tests: 78
- **checkWebRoles**: 19 tests
- **matchRouteRoles**: 7 tests  
- **checkApiRoles**: 26 tests
- **matchesPath**: 10 tests
- **matchesRoute**: 10 tests
- **integration scenarios**: 6 tests

## Test Structure

### checkWebRoles() Tests

**route group extraction** (5 tests)
1. Extracts route group from path correctly
2. Handles empty path as homepage
3. Handles path with leading slash
4. Handles path without leading slash (NOT_FOUND - no normalization)
5. Uses homepage as default for empty string

**route file loading** (2 tests)
1. Returns NOT_FOUND when route file does not exist
2. Loads route file based on first path segment

**route matching** (8 tests)
1. Returns NOT_FOUND when route is not defined in meta file
2. Returns ALLOWED for public routes without roles
3. Returns ALLOWED for public routes without user (guest access)
4. Returns ALLOWED when user has required role
5. Returns NOT_ALLOWED when user lacks required role
6. Returns ALLOWED when user has one of multiple required roles
7. Returns ALLOWED when super_admin accesses any protected route
8. Returns NOT_EXISTS when no user and route requires auth

**wildcard matching** (4 tests)
1. Matches wildcard routes with suffix path
2. Matches wildcard routes at exact prefix
3. Matches wildcard routes with deep nested paths
4. Does not match wildcard when user lacks role

### matchRouteRoles() Tests

Protected method testing via Reflection:

1. **Exact Match** - Returns roles for exact path match
2. **No Match** - Returns null when no match found
3. **Wildcard Suffix** - Matches wildcard pattern with suffix
4. **Wildcard Exact** - Matches wildcard pattern at exact prefix
5. **Exact Preference** - Prefers exact match over wildcard
6. **Public Routes** - Returns empty array for public routes
7. **First Match** - Matches first wildcard when multiple patterns exist

### checkApiRoles() Tests

**input validation** (4 tests)
1. Returns NOT_FOUND when to path is missing
2. Returns NOT_FOUND when to path is null
3. Defaults to GET method when not specified
4. Converts method to uppercase

**path normalization** (2 tests)
1. Removes /api prefix from path
2. Handles path without /api prefix

**route matching** (7 tests)
1. Returns NOT_EXISTS when route is not defined
2. Returns ALLOWED for public routes without roles
3. Returns ALLOWED when user has required role
4. Returns NOT_ALLOWED when user lacks required role
5. Returns NOT_ALLOWED when no user provided for protected route
6. Returns ALLOWED when super_admin accesses any protected route
7. Returns ALLOWED when user has one of multiple required roles

**wildcard method matching** (3 tests)
1. Matches any method with * wildcard
2. Matches GET with * wildcard
3. Matches DELETE with * wildcard

**path parameter matching** (4 tests)
1. Matches route with :id parameter
2. Matches route with multiple parameters
3. Does not match when path segment count differs
4. Matches numeric IDs with :id parameter

### matchesPath() Tests

Private static method testing via Reflection:

1. **Exact Match** - Matches exact paths
2. **Different Paths** - Does not match different paths
3. **Single Wildcard** - Matches path with :id wildcard
4. **Multiple Wildcards** - Matches path with multiple wildcards
5. **Segment Count** - Does not match when segment count differs
6. **Non-Wildcard Mismatch** - Does not match when non-wildcard segments differ
7. **Trailing Slashes** - Handles paths with trailing slashes
8. **Leading Slashes** - Handles paths with leading slashes
9. **String Values** - Matches wildcard with string values
10. **Complex Paths** - Matches complex nested paths with wildcards

### matchesRoute() Tests

Private static method testing via Reflection:

1. **Exact Method and Path** - Matches exact method and path
2. **Different Methods** - Does not match different methods
3. **Different Paths** - Does not match different paths
4. **Wildcard Method** - Matches wildcard method with any method
5. **Path Parameters** - Matches route with path parameters
6. **Wildcard with Parameters** - Matches wildcard method with path parameters
7. **Missing Pattern Method** - Returns false when pattern is missing method
8. **Missing Route Method** - Returns false when route key is missing method
9. **Case Insensitive** - Is case insensitive for methods
10. **Complex Routes** - Matches complex routes with multiple parameters

### Integration Scenarios (6 tests)

1. **Super Admin Web** - Allows super_admin to access all web routes
2. **Super Admin API** - Allows super_admin to access all API routes
3. **No Roles** - Restricts user without roles from accessing protected routes
4. **Guest Public** - Handles guest users correctly for public routes
5. **Guest Protected** - Handles guest users correctly for protected routes
6. **Multiple Roles** - Handles user with multiple roles accessing routes

### Edge Cases (6 tests)

1. **Empty Web Roles** - Handles empty route_roles gracefully for web
2. **Empty API Roles** - Handles empty route_roles gracefully for API
3. **Malformed Data** - Handles malformed API data gracefully
4. **Long Paths** - Handles very long paths
5. **Special Characters** - Handles paths with special characters
6. **Case Sensitivity** - Handles case sensitivity in paths

## Test Dependencies

### Models & Classes
- User (Spatie\Permission\Traits\HasRoles)
- RouteService
- RouteResult enum (ALLOWED, NOT_ALLOWED, NOT_EXISTS, NOT_FOUND)
- Role (Spatie\Permission\Models\Role)

### Testing Tools
- Reflection - Access private/protected methods
- RefreshDatabase - Clean database per test
- User factories - Test user creation

## Running Tests

```bash
# Run all RouteService tests
php artisan test --filter=RouteServiceTest

# Run specific describe block
php artisan test --filter="RouteServiceTest::checkWebRoles"
php artisan test --filter="RouteServiceTest::checkApiRoles"

# Run with coverage
php artisan test --filter=RouteServiceTest --coverage
```

## Test Patterns Used

- **describe/it syntax**: Organized tests by method and functionality
- **beforeEach**: Setup service instance and test route files
- **afterEach**: Cleanup test route files
- **Reflection**: Access private/protected methods for unit testing
- **Factory pattern**: User and role creation
- **File system mocking**: Create/delete test route meta files

## Key Behaviors Tested

### Web Route Authorization Flow

```php
1. Extract route group from first path segment
2. Load route metadata from routes/meta/web/{group}.php
3. Match full path against role definitions
4. Check user roles against required roles
5. Super admin bypasses all role requirements
6. Return appropriate RouteResult
```

**RouteResult States:**
- `ALLOWED`: User has access
- `NOT_ALLOWED`: User logged in but lacks role
- `NOT_EXISTS`: Guest user trying protected route
- `NOT_FOUND`: Route not defined in meta file

### API Route Authorization Flow

```php
1. Validate input (to path, method)
2. Normalize path (remove /api prefix)
3. Build route key: "METHOD /path"
4. Match against pattern with wildcards
5. Check user roles (super_admin auto-allowed)
6. Return RouteResult
```

### Path Matching Algorithm

**Exact Match First:**
```php
/admin/users => ['admin']  // Exact match
```

**Wildcard Match Second:**
```php
/admin/* => ['admin']      // Matches /admin and /admin/anything
```

**Parameter Wildcards:**
```php
:id, :userId, :schoolId   // Match any segment
/users/:id => /users/123  // Matches
GET /users/:id => GET /users/123  // Matches with method
```

**Method Wildcards:**
```php
* /users => Matches GET, POST, DELETE, etc.
```

### Route Group Extraction

```php
'/admin/users/create' => 'admin'
'/test/something' => 'test'
'/' => 'homepage'
'' => 'homepage'
'/prefix/suffix' => 'prefix'
```

Loads: `routes/meta/web/{group}.php`

### Role Requirements

**Empty Array** = Public route (no auth required)
```php
'/public/page' => []
```

**With Roles** = Protected route
```php
'/admin/users' => ['admin']
'/courses' => ['admin', 'teacher']  // OR logic
```

**Super Admin** = Always allowed (hardcoded bypass)
```php
if ($user->hasRole('super_admin')) return ALLOWED;
```

### Path Normalization

**Web Routes:**
- Path passed as-is to matchRouteRoles
- No automatic leading slash addition
- `'test/admin'` won't match `'/test/admin'`

**API Routes:**
- Removes `/api` prefix if present
- `/api/users` becomes `/users`
- Method converted to uppercase
- `'post'` becomes `'POST'`

## Route Meta File Structure

Located in `routes/meta/web/` and `routes/meta/api/`

```php
<?php

return [
    'roles' => [
        // Exact matches
        '/admin/login' => [],
        '/admin/users' => ['admin'],
        
        // Wildcard patterns
        '/admin/*' => ['admin'],
        
        // Multiple roles (OR)
        '/courses' => ['admin', 'teacher'],
        
        // API routes with methods
        'GET /users' => ['admin'],
        'POST /users' => ['admin'],
        '* /public' => [],  // All methods
        'DELETE /users/:id' => ['admin'],
    ]
];
```

## Coverage Areas

✅ Route group extraction from paths  
✅ Route meta file loading  
✅ Exact path matching  
✅ Wildcard path matching (prefix/*)  
✅ Parameter wildcards (:id, :userId)  
✅ Method wildcards (*)  
✅ Method case insensitivity  
✅ Role-based authorization  
✅ Super admin bypass  
✅ Guest user handling  
✅ Public route access  
✅ Multiple role requirements (OR logic)  
✅ API path normalization (/api removal)  
✅ Missing route handling  
✅ Empty roles handling  
✅ Malformed input handling  
✅ Path edge cases (long, special chars)  

## Security Considerations Tested

1. **Guest Access Control**
   - Public routes: ALLOWED
   - Protected routes: NOT_EXISTS

2. **Authenticated Access**
   - With role: ALLOWED
   - Without role: NOT_ALLOWED

3. **Super Admin Privilege**
   - Bypasses all role checks
   - Always returns ALLOWED for any route

4. **Missing Routes**
   - NOT_FOUND when not in meta file
   - Prevents unauthorized access by default

5. **Path Traversal Protection**
   - Exact matching prevents similar path exploitation
   - Wildcard scope limited to prefix

## Integration with Spatie Permission

```php
// Methods used from Spatie package
$user->hasRole('role_name')       // Single role check
$user->hasAnyRole(['role1', 'role2'])  // Multiple role check (OR)

// Roles created in tests
Role::create(['name' => 'admin', 'guard_name' => 'web']);
$user->assignRole('admin');
$user->assignRole(['admin', 'teacher']);
```

## Reflection Usage

Tests access private methods using PHP Reflection:

```php
$reflection = new ReflectionClass($this->service);
$method = $reflection->getMethod('matchRouteRoles');
$method->setAccessible(true);
$result = $method->invoke($this->service, $path, $roleMap);
```

For static methods:
```php
$reflection = new ReflectionClass(RouteService::class);
$method = $reflection->getMethod('matchesPath');
$method->setAccessible(true);
$result = $method->invoke(null, $pattern, $path);
```

## Test File Setup

**beforeEach:**
- Creates RouteService instance
- Creates test roles (admin, teacher, student, super_admin)
- Creates test route meta file: `routes/meta/web/test.php`

**afterEach:**
- Deletes test route meta file
- Database cleaned by RefreshDatabase trait

**Test Route File Content:**
```php
return [
    'roles' => [
        '/test/public' => [],
        '/test/admin' => ['admin'],
        '/test/teacher' => ['teacher'],
        '/test/multi' => ['admin', 'teacher'],
        '/test/prefix/*' => ['admin'],
    ]
];
```

## Common Test Patterns

**Testing Authorization:**
```php
$user = User::factory()->create();
$user->assignRole('admin');
$result = $this->service->checkWebRoles($user, '/test/admin');
expect($result)->toBe(RouteResult::ALLOWED);
```

**Testing Guest Access:**
```php
$result = $this->service->checkWebRoles(null, '/test/public');
expect($result)->toBe(RouteResult::ALLOWED);
```

**Testing API Routes:**
```php
$route_roles = ['roles' => ['GET /users' => ['admin']]];
$result = $this->service->checkApiRoles($user, [
    'to' => '/users',
    'method' => 'GET'
], $route_roles);
```

## Limitations & Known Behavior

1. **Path Normalization**: Web routes don't auto-add leading slash
   - `'test/admin'` won't match `'/test/admin'` definition

2. **Wildcard Scope**: `/admin/*` matches `/admin` AND `/admin/anything`
   - Intentional for ease of configuration

3. **Super Admin**: Hardcoded bypass, cannot be disabled
   - Always returns ALLOWED regardless of route definition

4. **Case Sensitivity**: 
   - Methods: Case insensitive (GET = get)
   - Paths: Case sensitive (/Admin ≠ /admin)

5. **Route Loading**: Based on first path segment only
   - `/admin/users/create` all load `routes/meta/web/admin.php`

6. **Missing Files**: Returns NOT_FOUND, not error
   - Graceful degradation for undefined routes

## Performance Notes

- File loading cached per request (include caches)
- Reflection overhead minimal for private method tests
- Role checks delegated to Spatie (uses database/cache)
- Wildcard matching O(n) where n = number of patterns

## Use Cases

This service is used for:
- Inertia.js route protection (web)
- API endpoint authorization (API)
- Dynamic navigation rendering (role-based)
- Middleware authorization decisions
- Frontend route guarding

## Related Files

- `app/Enums/RouteResult.php` - Return value enum
- `routes/meta/web/*.php` - Web route definitions
- `routes/meta/api/*.php` - API route definitions (if exists)
- `app/Models/User.php` - User model with HasRoles trait
- Spatie Permission package - Role management
