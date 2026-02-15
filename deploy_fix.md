# Deployment Steps for Cloudways Server

After uploading the updated `TeachingCourseService.php` file, run these commands via SSH:

```bash
# 1. Clear OPcache (critical!)
sudo service php8.2-fpm reload

# OR if that doesn't work:
sudo service php-fpm reload

# 2. Clear Laravel caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. Optimize autoloader
composer dump-autoload --optimize

# 4. Verify the fix is deployed
php artisan tinker
```

Then in tinker, run:
```php
$service = new App\Services\TeachingCourseService();
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('resolveStudentReferenceFromNumeric');
$method->setAccessible(true);
$result = $method->invoke($service, 1156, 1);
print_r($result);
// Should show: ['user_id' => (some number), 'import116_id' => null]
exit
```

## If issue persists:

Check that these 3 fixes are in the file:

1. **Line 385-391**: `createStudentUser()` checks for existing users
2. **Line 419-432**: `import116_id` field converts to user_id
3. **Line 459-470**: Fallback converts to user_id

## Quick verification command:

```bash
grep -A 5 "if (isset(\$data\['import116_id'\])" app/Services/TeachingCourseService.php
```

Should show the code trying to convert import116_id to user_id.
