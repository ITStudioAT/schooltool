<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/**
 * Enable a licensed module explicitly for feature tests exercising its routes.
 */
function enableSchoolToolModuleForTests(School $school, string $module): void
{
    SchoolTool::query()->updateOrCreate(
        ['school_id' => $school->id],
        [
            "{$module}_visible_admin" => true,
            "{$module}_visible_user" => true,
        ],
    );
}

/**
 * Attach an active legacy school licence for route-level feature tests.
 */
function grantSchoolToolLicenceForTests(School $school, string $licenceName): void
{
    $licence = Licence::query()->firstOrCreate(
        ['name' => $licenceName],
        [
            'long_name' => $licenceName,
            'is_selectable' => true,
        ],
    );

    $school->licences()->syncWithoutDetaching([
        $licence->id => ['valid_until' => now()->addYear()->toDateString()],
    ]);
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
