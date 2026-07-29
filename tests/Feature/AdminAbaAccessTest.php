<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'long_name' => 'ABA Test School',
        'short_name' => 'ABT',
    ]);

    $this->schoolyearA = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2025/2026',
    ]);

    $this->schoolyearB = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2026/2027',
    ]);

    $this->otherSchool = School::factory()->create([
        'long_name' => 'Foreign School',
        'short_name' => 'FRG',
    ]);

    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
        'name' => '2030/2031',
    ]);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'aba_teacher', 'guard_name' => 'web']);

    $this->abaLicence = Licence::firstOrCreate(
        ['name' => 'ABA'],
        [
            'long_name' => 'ABA',
            'is_selectable' => true,
        ]
    );
});

function createAbaUser(School $school, Schoolyear $schoolyear, string $role): User
{
    $user = User::factory()->create([
        'email' => $role.'-'.fake()->unique()->safeEmail(),
        'first_name' => 'Aba',
        'last_name' => 'Tester',
        'password' => Hash::make('password123'),
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $user->assignRole($role);

    return $user;
}

function attachActiveAbaLicence(School $school, Licence $licence): void
{
    $school->licences()->syncWithoutDetaching([
        $licence->id => [
            'valid_until' => now()->addYear()->toDateString(),
        ],
    ]);
}

it('hides and denies aba for users without aba_teacher role', function () {
    attachActiveAbaLicence($this->school, $this->abaLicence);
    $user = createAbaUser($this->school, $this->schoolyearA, 'admin');

    $this->actingAs($user);

    $this->get('/admin/aba')->assertForbidden();

    $config = $this->getJson('/api/admin/config')->assertSuccessful()->json();
    $menuTitles = collect($config['menu'])->pluck('title');
    expect($menuTitles)->not->toContain('ABA');
    expect((bool) data_get($config, 'capabilities.aba'))->toBeFalse();
});

it('hides and denies aba for aba_teacher without aba licence', function () {
    $user = createAbaUser($this->school, $this->schoolyearA, 'aba_teacher');

    $this->actingAs($user);

    $this->get('/admin/aba')->assertForbidden();

    $config = $this->getJson('/api/admin/config')->assertSuccessful()->json();
    $menuTitles = collect($config['menu'])->pluck('title');
    expect($menuTitles)->not->toContain('ABA');
    expect((bool) data_get($config, 'capabilities.aba'))->toBeFalse();
});

it('shows and allows aba for aba_teacher with active aba licence', function () {
    attachActiveAbaLicence($this->school, $this->abaLicence);
    $user = createAbaUser($this->school, $this->schoolyearA, 'aba_teacher');

    $this->actingAs($user);

    $this->get('/admin/aba')->assertSuccessful()->assertViewIs('spa::admin');

    $config = $this->getJson('/api/admin/config')->assertSuccessful()->json();
    $menuTitles = collect($config['menu'])->pluck('title');
    expect($menuTitles)->toContain('ABA');
    expect((bool) data_get($config, 'capabilities.aba'))->toBeTrue();
});

it('hides and denies aba when module visibility is disabled app-wide', function () {
    attachActiveAbaLicence($this->school, $this->abaLicence);
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'aba_visible_admin' => false,
        'aba_visible_user' => true,
    ]);

    $user = createAbaUser($this->school, $this->schoolyearA, 'aba_teacher');

    $this->actingAs($user);

    $this->get('/admin/aba')->assertForbidden();

    $config = $this->getJson('/api/admin/config')->assertSuccessful()->json();
    $menuTitles = collect($config['menu'])->pluck('title');
    expect($menuTitles)->not->toContain('ABA');
    expect((bool) data_get($config, 'capabilities.aba'))->toBeFalse();
});

it('returns the current selected schoolyear in admin config', function () {
    attachActiveAbaLicence($this->school, $this->abaLicence);
    $user = createAbaUser($this->school, $this->schoolyearA, 'aba_teacher');

    $this->actingAs($user, 'sanctum');

    $this->getJson('/api/admin/config')
        ->assertSuccessful()
        ->assertJsonPath('selected_schoolyear.id', $this->schoolyearA->id)
        ->assertJsonPath('selected_schoolyear.name', '2025/2026');
});

it('loads schoolyears for aba dialog from the current user school only', function () {
    attachActiveAbaLicence($this->school, $this->abaLicence);
    $user = createAbaUser($this->school, $this->schoolyearA, 'aba_teacher');

    $this->actingAs($user, 'sanctum');

    $response = $this->getJson('/api/admin/aba/schoolyears');

    $response->assertSuccessful();
    $ids = collect($response->json())->pluck('id')->all();

    expect($ids)
        ->toContain($this->schoolyearA->id)
        ->toContain($this->schoolyearB->id)
        ->not->toContain($this->otherSchoolyear->id);
});

it('updates user schoolyear_id through aba schoolyear endpoint and reflects it in config', function () {
    attachActiveAbaLicence($this->school, $this->abaLicence);
    $user = createAbaUser($this->school, $this->schoolyearA, 'aba_teacher');

    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/admin/aba/schoolyears/set_active', [
        'schoolyear_id' => $this->schoolyearB->id,
    ])->assertSuccessful()
        ->assertJsonFragment([
            'id' => $this->schoolyearB->id,
            'name' => '2026/2027',
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'schoolyear_id' => $this->schoolyearB->id,
    ]);

    $this->getJson('/api/admin/config')
        ->assertSuccessful()
        ->assertJsonPath('selected_schoolyear.id', $this->schoolyearB->id)
        ->assertJsonPath('selected_schoolyear.name', '2026/2027');
});

it('rejects invalid schoolyear_id for aba schoolyear switch', function () {
    attachActiveAbaLicence($this->school, $this->abaLicence);
    $user = createAbaUser($this->school, $this->schoolyearA, 'aba_teacher');

    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/admin/aba/schoolyears/set_active', [
        'schoolyear_id' => 999999,
    ])->assertStatus(422);
});

it('rejects switching to a schoolyear from a different school', function () {
    attachActiveAbaLicence($this->school, $this->abaLicence);
    $user = createAbaUser($this->school, $this->schoolyearA, 'aba_teacher');

    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/admin/aba/schoolyears/set_active', [
        'schoolyear_id' => $this->otherSchoolyear->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('schoolyear_id');
});

it('denies aba schoolyear endpoints without aba access', function () {
    attachActiveAbaLicence($this->school, $this->abaLicence);
    $userWithoutRole = createAbaUser($this->school, $this->schoolyearA, 'admin');

    $this->actingAs($userWithoutRole, 'sanctum');
    $this->getJson('/api/admin/aba/schoolyears')->assertForbidden();
    $this->postJson('/api/admin/aba/schoolyears/set_active', [
        'schoolyear_id' => $this->schoolyearB->id,
    ])->assertForbidden();
});

it('defines schoolyear switch button and persistent dialog in aba page', function () {
    $abaPageContent = file_get_contents(resource_path('js/pages/admin/aba/Aba.vue'));

    expect($abaPageContent)
        ->toContain("key: 'overview'")
        ->toContain("key: 'schoolyear'")
        ->toContain("label: 'Überblick'")
        ->toContain('label: this.currentSchoolyearLabel')
        ->toContain('<v-dialog v-model="schoolyearDialogOpen" persistent')
        ->toContain('@click="cancelSchoolyearSwitch"')
        ->toContain('@click="confirmSchoolyearSwitch"');
});

it('keeps abort and ui refresh logic for schoolyear switch in aba page', function () {
    $abaPageContent = file_get_contents(resource_path('js/pages/admin/aba/Aba.vue'));

    expect($abaPageContent)
        ->toContain('this.schoolyearDialogSelection = this.selectedSchoolyearId')
        ->toContain('this.schoolyearDialogOpen = false')
        ->toContain("await axios.post('/api/admin/aba/schoolyears/set_active'")
        ->toContain('await this.adminStore.loadConfig()');
});

it('keeps overview content as the existing aba main view', function () {
    $overviewContent = file_get_contents(resource_path('js/pages/admin/aba/components/Overview.vue'));

    expect($overviewContent)
        ->toContain('Meine ABAs')
        ->toContain('Schuljahr')
        ->toContain('Neue ABA erstellen')
        ->toContain('mdi-pencil')
        ->toContain('Upload')
        ->toContain('Details')
        ->toContain('Dateien')
        ->toContain("await axios.get('/api/admin/abas')")
        ->toContain("await axios.post('/api/admin/abas'")
        ->toContain('await axios.put(`/api/admin/abas/${this.editingAbaId}`')
        ->toContain('/api/admin/aba/uploads/chunk')
        ->toContain('/attachments/from-temp')
        ->toContain('/attachments/${attachment.id}')
        ->toContain('file-pond')
        ->toContain('/admin/aba/details/${abaId}')
        ->not->toContain('Analyse')
        ->not->toContain('Ergebnisse')
        ->not->toContain('/api/admin/abas/${abaId}/analysis')
        ->not->toContain('/admin/aba/results/${abaId}');
});

it('keeps aba router reduced to overview and detail page', function () {
    $routerContent = file_get_contents(resource_path('routes/admin.js'));

    expect($routerContent)
        ->toContain("{ path: '/admin/aba', component: Aba, meta: { capability: 'aba' } }")
        ->toContain("{ path: '/admin/aba/details/:abaId', component: AbaDetails, meta: { capability: 'aba' } }")
        ->not->toContain('/admin/aba/results/:abaId')
        ->not->toContain('/admin/aba/ai-settings');
});

it('keeps aba navigation focused on overview and schoolyear', function () {
    $abaPageContent = file_get_contents(resource_path('js/pages/admin/aba/Aba.vue'));

    expect($abaPageContent)
        ->toContain("key: 'overview'")
        ->toContain("key: 'schoolyear'")
        ->not->toContain("key: 'ai-settings'")
        ->not->toContain('/admin/aba/ai-settings');
});

it('ships an aba detail page with extraction access and back navigation', function () {
    $detailPageContent = file_get_contents(resource_path('js/pages/admin/aba/AbaDetails.vue'));

    expect($detailPageContent)
        ->toContain('ABA Details')
        ->toContain('aba.title')
        ->toContain('aba.student_name')
        ->toContain('aba.student_class')
        ->toContain('aba.schoolyear_name')
        ->toContain('Hauptdokument')
        ->toContain('Weitere Dokumente')
        ->toContain('Extraktion starten')
        ->toContain('Extraktion 2')
        ->toContain('await axios.get(`/api/admin/abas/${this.abaId}`)')
        ->toContain('await axios.get(`/api/admin/abas/${this.abaId}/extraction`)')
        ->toContain('await axios.get(`/api/admin/abas/${this.abaId}/extraction/parsel`)')
        ->toContain('await axios.get(`/api/admin/abas/${this.abaId}/extraction/compare`)')
        ->toContain('overwrite_existing_fields: this.overwriteExistingExtractionFields')
        ->toContain('await axios.post(`/api/admin/abas/${this.abaId}/extraction`, {')
        ->toContain('await axios.post(`/api/admin/abas/${this.abaId}/extraction/parsel`)')
        ->toContain("\$router.push('/admin/aba')");
});

it('opens upload from files dialog instead of aba list row', function () {
    $overviewContent = file_get_contents(resource_path('js/pages/admin/aba/components/Overview.vue'));

    expect($overviewContent)
        ->toContain('<v-dialog v-model="filesDialogOpen"')
        ->toContain('@click="openUploadDialog(selectedFilesAba)"')
        ->toContain('@click="openFilesDialog(aba)"')
        ->not->toContain('@click="openUploadDialog(aba)"');
});
