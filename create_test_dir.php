<?php
// Run AdminNavigationService tests
chdir(__DIR__);

echo "Running AdminNavigationService tests...\n\n";
passthru('php artisan test --filter=AdminNavigationServiceTest', $return_code);

exit($return_code);
