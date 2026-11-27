<?php

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\AdminNavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function versionFourFeatureFixture(string $role = 'super_admin', array $features = []): array
{
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    $tool = SchoolTool::factory()->create([
        'school_id' => $school->id,
        'version_four_visible' => false,
        'version_four_features' => $features,
        'register_visible_admin' => true,
    ]);
    $user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'is_active' => true]);
    $user->assignRole(Role::findOrCreate($role, 'web'));

    return [$user, $tool, $year];
}

test('saves a superadmin feature without changing other features or releasing version four', function (string $feature) {
    [$user, $tool] = versionFourFeatureFixture(features: ['elections' => false, 'matura' => true, 'homepage' => true]);

    $this->actingAs($user)->postJson('/api/admin/school_tools/save_module_statuses', ['data' => [
        'id' => $tool->id,
        'version_four_features' => [$feature => false],
        'register_visible_admin' => false,
    ]])->assertOk()
        ->assertJsonPath("version_four_features.{$feature}", false)
        ->assertJsonPath('version_four_features.elections', false)
        ->assertJsonPath('version_four_features.'.($feature === 'matura' ? 'homepage' : 'matura'), true)
        ->assertJsonPath('version_four_visible', false);

    expect($tool->fresh()->version_four_features)->toMatchArray([$feature => false, 'elections' => false]);
    $this->assertDatabaseHas('school_tools', ['id' => $tool->id, 'register_visible_admin' => true, 'version_four_visible' => false]);
})->with(['elections', 'matura', 'homepage']);

test('returns 403 when an ordinary admin tries to change superadmin visibility', function () {
    [$user, $tool] = versionFourFeatureFixture('admin');

    $this->actingAs($user)->postJson('/api/admin/school_tools/save_module_statuses', ['data' => [
        'id' => $tool->id,
        'version_four_features' => ['homepage' => true],
    ]])->assertForbidden();

    expect($tool->fresh()->version_four_features)->toBe([]);
    $this->getJson('/api/admin/school_tools/load_config')->assertOk()->assertJsonMissingPath('version_four_features');
});

test('validates feature names and boolean values', function (array $features, string $error) {
    [$user, $tool] = versionFourFeatureFixture();

    $this->actingAs($user)->postJson('/api/admin/school_tools/save_module_statuses', ['data' => [
        'id' => $tool->id,
        'version_four_features' => $features,
    ]])->assertUnprocessable()->assertJsonValidationErrors($error);

    expect($tool->fresh()->version_four_features)->toBe([]);
})->with([
    [['unrecognised' => true], 'data.version_four_features'],
    [['homepage' => 'enabled'], 'data.version_four_features.homepage'],
    [[], 'data.version_four_features'],
]);

test('enforces each Helpers switch for superadmins in navigation and direct requests', function (bool $elections, bool $matura) {
    [$user, $tool, $year] = versionFourFeatureFixture(features: ['elections' => $elections, 'matura' => $matura]);
    $this->actingAs($user);

    $this->getJson(route('admin.helpers.classes', ['schoolyear_id' => $year->id]))->assertStatus($elections ? 200 : 403);
    $this->getJson(route('admin.matura.index', ['schoolyear_id' => $year->id]))->assertStatus($matura ? 200 : 403);

    $navigation = app(AdminNavigationService::class);
    $menu = $navigation->dashboardMenu();
    expect($navigation->routeCapabilities($user, $menu)['helpers'])->toBe($elections || $matura);
})->with([[true, false], [false, true], [false, false], [true, true]]);

test('uses the stored homepage design for every visitor and both public entry endpoints', function (?string $role, bool $enabled) {
    [$user] = versionFourFeatureFixture($role ?? 'admin', ['homepage' => $enabled]);
    if ($role !== null) {
        $this->actingAs($user);
    }

    $this->getJson('/api/homepage/login_schools')->assertOk()->assertJsonPath('homepage_design_enabled', $enabled);
    $this->getJson('/api/homepage/student/config')->assertOk()->assertJsonPath('homepage_design_enabled', $enabled);
})->with([
    'guest, on' => [null, true],
    'guest, off' => [null, false],
    'teacher, on' => ['teacher', true],
    'teacher, off' => ['teacher', false],
    'superadmin, on' => ['super_admin', true],
    'superadmin, off' => ['super_admin', false],
]);

test('uses the stored homepage default when the setting is absent', function () {
    versionFourFeatureFixture();

    $this->getJson('/api/homepage/login_schools')->assertOk()->assertJsonPath('homepage_design_enabled', true);
    $this->getJson('/api/homepage/student/config')->assertOk()->assertJsonPath('homepage_design_enabled', true);
});
