# Database Safety System

## 🚨 CRITICAL WARNING

**Tests using `RefreshDatabase` trait will DROP ALL TABLES and recreate them. If tests run against your production database, ALL PRODUCTION DATA WILL BE PERMANENTLY DELETED.**

## Safety Measures Implemented

### 1. Database Safety Service Provider

- **Location**: `app/Providers/DatabaseSafetyServiceProvider.php`
- **Purpose**: Automatically checks database configuration when application boots in testing environment
- **Action**: Throws `RuntimeException` if tests attempt to run against production database
- **Protected databases**: `schooltool`, `production_db`, `live_db`
- **Test databases**: `pest_test`, `testing`, `test`

### 2. Safety Configuration File

- **Location**: `config/database-safety.php`
- **Purpose**: Documents and enforces safety rules
- **Features**:
    - Clear safety rules documentation
    - Environment-specific database configurations
    - Verification functions
    - Emergency procedures

### 3. Safety Check Command

- **Command**: `php artisan db:safety-check`
- **Purpose**: Manual verification of database safety
- **Output**: Detailed safety status report
- **Action**: Throws exception if unsafe configuration detected

### 4. PHPUnit Configuration

- **File**: `phpunit.xml`
- **Test database**: `pest_test` (line 27)
- **Isolation**: Tests run in separate database to protect production data

## How It Works

### Automatic Protection

When the application boots in testing environment (`APP_ENV=testing`):

1. `DatabaseSafetyServiceProvider` checks current database configuration
2. If database name matches production database (`schooltool`), throws exception
3. Prevents tests from running against production data

### Manual Verification

Run safety check anytime:

```bash
php artisan db:safety-check
```

Example safe output:

```
🔒 Database Safety Check

┌─────────────────┬─────────────────┐
│ Setting         │ Value           │
├─────────────────┼─────────────────┤
│ Environment     │ testing         │
│ Current Database│ pest_test       │
│ Safe for Testing│ ✅ Yes          │
│ Status          │ Safe: Tests are using a test database. │
│ Checked At      │ 2024-01-15 10:30:00 │
└─────────────────┴─────────────────┘

✅ Database safety check passed. Tests are safe to run.
```

Example unsafe output (THROWS EXCEPTION):

```
🚨 CRITICAL WARNING:
Tests are configured to run against a production database!
Running tests will DELETE ALL PRODUCTION DATA!

Immediate Actions Required:
1. STOP all test execution
2. Verify phpunit.xml has DB_DATABASE=pest_test
3. Ensure the "pest_test" database exists
4. Never run tests against "schooltool" database

RuntimeException: Database safety check failed: Tests would delete production data...
```

## Safety Rules (NON-NEGOTIABLE)

1. **Tests MUST NEVER delete or modify real production data**
2. **Tests MUST use a separate testing database** (configured in phpunit.xml)
3. **Migrations run during tests MUST only affect the testing database**
4. **Database operations in tests MUST be isolated and cleaned up after each test**
5. **Real user data MUST be protected from accidental deletion or modification**

## Setup Instructions

### 1. Create Test Database

```sql
CREATE DATABASE pest_test;
GRANT ALL PRIVILEGES ON pest_test.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Verify Configuration

```bash
# Check current configuration
php artisan db:safety-check

# Run a simple test to verify
php artisan test --filter=ExampleTest
```

### 3. Regular Safety Audits

Add to your deployment checklist:

```bash
# Before running any tests
php artisan db:safety-check

# After configuration changes
php artisan db:safety-check
```

## Emergency Procedures

If you suspect tests have affected production data:

1. **IMMEDIATELY STOP** all operations
2. **Check database backups** in `storage/backups/`
3. **Contact system administrator**
4. **DO NOT attempt to fix** without proper backup verification
5. **Use latest verified backup** to restore data

## Testing Best Practices

### Safe Test Structure

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

// This is SAFE when using pest_test database
uses(RefreshDatabase::class);

test('example test', function () {
    // Test code here - runs in isolated pest_test database
});
```

### Unsafe Practices (AVOID)

- Running tests without `APP_ENV=testing`
- Modifying `.env` to use production database for tests
- Disabling safety checks
- Running `php artisan migrate:fresh` on production database

## Configuration Files

### phpunit.xml (Lines 26-29)

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="pest_test"/>
<env name="DB_USERNAME" value="root"/>
<env name="DB_PASSWORD" value=""/>
```

### .env (Production - DO NOT MODIFY FOR TESTS)

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=schooltool    # PRODUCTION DATABASE
DB_USERNAME=root
DB_PASSWORD=
```

## Troubleshooting

### Error: "Tests are attempting to run against production database"

**Cause**: Tests running against `schooltool` database instead of `pest_test`

**Solution**:

1. Verify `phpunit.xml` has `DB_DATABASE=pest_test`
2. Ensure `pest_test` database exists
3. Run `php artisan db:safety-check` to verify

### Error: "pest_test database doesn't exist"

**Solution**:

```sql
CREATE DATABASE pest_test;
```

### Error: "Permission denied for pest_test database"

**Solution**:

```sql
GRANT ALL PRIVILEGES ON pest_test.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```

## Monitoring

Check safety status regularly:

```bash
# Quick status check
php artisan db:safety-check

# View current configuration
php artisan config:show database.connections.mysql.database

# Check environment
php artisan config:show app.env
```

## Remember

**NEVER DISABLE THESE SAFETY CHECKS.** They exist to prevent catastrophic data loss. If you need to modify them, consult with the system administrator first.

---

_Last updated: April 2024_  
_Safety system version: 1.0_
