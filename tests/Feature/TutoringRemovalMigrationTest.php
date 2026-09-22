<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config([
        'database.default' => 'tutoring_removal_test',
        'database.connections.tutoring_removal_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'cache.default' => 'array',
        'permission.cache.store' => 'array',
    ]);
    DB::purge('tutoring_removal_test');
    Schema::clearResolvedInstance('db.schema');
});

afterEach(function (): void {
    DB::purge('tutoring_removal_test');
});

function runTutoringRemovalMigrations(): void
{
    foreach (['2026_09_23_004559_remove_tutoring_schema.php', '2026_09_23_004600_remove_tutoring_roles_and_licences.php'] as $file) {
        (require database_path('migrations/'.$file))->up();
    }
}

test('retirement tolerates absent module schema and assignments repeatedly', function (): void {
    runTutoringRemovalMigrations();
    runTutoringRemovalMigrations();

    expect(Schema::hasTable('tutoring_offers'))->toBeFalse()
        ->and(Schema::hasTable('users'))->toBeFalse();
});

test('retirement removes populated legacy data while retaining shared accounts licences permissions and teacher indexes', function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('email');
        $table->timestamp('confirmed_at')->nullable();
        $table->json('tutoring_filter')->nullable();
    });
    Schema::create('school_tools', function (Blueprint $table): void {
        $table->id();
        $table->boolean('register_visible_user')->default(true);
        $table->unsignedInteger('material_max_file_upload_size')->default(20480);
        foreach (['tutoring_student_must_be_confirmed', 'tutoring_max_offers_per_student', 'may_visible_for_other_schools', 'tutoring_visible_admin', 'tutoring_visible_user', 'tutoring_user_test_mode', 'tutoring_user_comming_soon'] as $column) {
            $table->unsignedInteger($column)->default(1);
        }
        $table->string('tutoring_confirmer_email')->nullable();
        $table->string('tutoring_status')->default('active');
    });
    Schema::create('tutoring_subjects', function (Blueprint $table): void {
        $table->id();
    });
    Schema::create('tutoring_offers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('subject_id')->constrained('tutoring_subjects');
        $table->foreignId('user_id')->constrained('users');
    });
    Schema::create('tutoring_offer_requests', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('offer_id')->constrained('tutoring_offers');
    });
    Schema::create('teachers', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('school_id');
        $table->string('email');
        $table->string('short');
        $table->string('last_name');
    });
    (require database_path('migrations/2026_07_28_095528_add_teacher_and_tutoring_query_indexes.php'))->up();

    foreach (['roles', 'permissions'] as $tableName) {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
    }
    Schema::create('model_has_roles', function (Blueprint $table): void {
        $table->foreignId('role_id')->constrained('roles');
        $table->unsignedBigInteger('model_id');
        $table->string('model_type');
    });
    Schema::create('role_has_permissions', function (Blueprint $table): void {
        $table->foreignId('role_id')->constrained('roles');
        $table->foreignId('permission_id')->constrained('permissions');
    });
    Schema::create('model_has_permissions', function (Blueprint $table): void {
        $table->foreignId('permission_id')->constrained('permissions');
        $table->unsignedBigInteger('model_id');
        $table->string('model_type');
    });
    Schema::create('licences', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->json('admin_role_names')->nullable();
        $table->json('user_role_names')->nullable();
        $table->json('licence_model')->nullable();
    });
    Schema::create('school_licences', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('licence_id')->constrained('licences');
        $table->json('licence_model')->nullable();
        $table->json('user_licence_assignments')->nullable();
    });
    foreach (['school_user_licences', 'licence_user_plans'] as $tableName) {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('licence_id')->constrained('licences');
            $table->string('role_name');
        });
    }

    DB::table('users')->insert([
        ['id' => 1, 'email' => 'shared@example.test', 'confirmed_at' => '2026-01-01 12:00:00', 'tutoring_filter' => '{"only_boys":true}'],
        ['id' => 2, 'email' => 'former-tutor@example.test', 'confirmed_at' => null, 'tutoring_filter' => null],
    ]);
    DB::table('school_tools')->insert(['id' => 1, 'register_visible_user' => true, 'material_max_file_upload_size' => 40960]);
    DB::table('tutoring_subjects')->insert(['id' => 1]);
    DB::table('tutoring_offers')->insert(['id' => 1, 'subject_id' => 1, 'user_id' => 1]);
    DB::table('tutoring_offer_requests')->insert(['id' => 1, 'offer_id' => 1]);
    DB::table('teachers')->insert(['id' => 1, 'school_id' => 1, 'email' => 'teacher@example.test', 'short' => 'TE', 'last_name' => 'Teacher']);
    DB::table('roles')->insert([
        ['id' => 1, 'name' => 'tutoring_user', 'guard_name' => 'web'],
        ['id' => 2, 'name' => 'tutoring_admin', 'guard_name' => 'web'],
        ['id' => 3, 'name' => 'teacher', 'guard_name' => 'web'],
        ['id' => 4, 'name' => 'custom_tutoring_reports', 'guard_name' => 'web'],
        ['id' => 5, 'name' => 'tutoring_user', 'guard_name' => 'api'],
    ]);
    DB::table('permissions')->insert(['id' => 1, 'name' => 'custom_tutoring_reports', 'guard_name' => 'web']);
    foreach ([1, 2, 3, 4, 5] as $roleId) {
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_id' => 1, 'model_type' => 'App\\Models\\User']);
        DB::table('role_has_permissions')->insert(['role_id' => $roleId, 'permission_id' => 1]);
    }
    DB::table('model_has_roles')->insert(['role_id' => 1, 'model_id' => 2, 'model_type' => 'App\\Models\\User']);
    DB::table('model_has_permissions')->insert(['permission_id' => 1, 'model_id' => 1, 'model_type' => 'App\\Models\\User']);

    $legacyModel = '{"user_licence_required_by_role":{"tutoring_user":true,"teacher":true},"user_licence_plans_by_role":{"tutoring_admin":[{"text":"old"}],"teacher":[{"text":"kept","price_per_year":"8"}]},"admin_role_names":["tutoring_admin","teacher"],"user_role_names":["tutoring_user","student"],"note":"tutoring_user"}';
    $legacyModel = json_decode($legacyModel, true);
    $legacyModel['affected_roles'] = ['tutoring_user', 'teacher', 'tutoring_admin', 'custom_tutoring_reports'];
    $legacyModel = json_encode($legacyModel, JSON_THROW_ON_ERROR);
    $expectedModel = json_decode($legacyModel, true);
    unset($expectedModel['user_licence_required_by_role']['tutoring_user'], $expectedModel['user_licence_plans_by_role']['tutoring_admin']);
    $expectedModel['admin_role_names'] = ['teacher'];
    $expectedModel['user_role_names'] = ['student'];
    $expectedModel['affected_roles'] = ['teacher', 'custom_tutoring_reports'];
    DB::table('licences')->insert([
        ['id' => 2, 'name' => 'Lehrertool', 'admin_role_names' => '["tutoring_admin","teacher"]', 'user_role_names' => '["student","tutoring_user"]', 'licence_model' => $legacyModel],
        ['id' => 77, 'name' => 'Nachhilfetool', 'admin_role_names' => null, 'user_role_names' => null, 'licence_model' => null],
        ['id' => 78, 'name' => 'Custom Nachhilfetool reports', 'admin_role_names' => null, 'user_role_names' => null, 'licence_model' => '{"untouched":"tutoring_user"}'],
    ]);
    DB::table('school_licences')->insert([
        ['id' => 1, 'licence_id' => 2, 'licence_model' => $legacyModel, 'user_licence_assignments' => '{"1":{"tutoring_user":{"valid_until":"2027-01-01"},"teacher":{"valid_until":"2027-01-01"}},"2":{"tutoring_admin":true},"legacy_note":"tutoring_user"}'],
        ['id' => 2, 'licence_id' => 77, 'licence_model' => null, 'user_licence_assignments' => null],
    ]);
    foreach (['school_user_licences', 'licence_user_plans'] as $table) {
        DB::table($table)->insert([
            ['id' => 1, 'licence_id' => 2, 'role_name' => 'teacher'],
            ['id' => 2, 'licence_id' => 2, 'role_name' => 'tutoring_user'],
            ['id' => 3, 'licence_id' => 77, 'role_name' => 'teacher'],
        ]);
    }
    Cache::store('array')->put(config('permission.cache.key'), ['stale' => true], 60);

    runTutoringRemovalMigrations();
    runTutoringRemovalMigrations();

    foreach (['tutoring_subjects', 'tutoring_offers', 'tutoring_offer_requests'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
    expect(Schema::getColumnListing('school_tools'))->toBe(['id', 'register_visible_user', 'material_max_file_upload_size'])
        ->and(Schema::hasColumn('users', 'tutoring_filter'))->toBeFalse()
        ->and(DB::table('users')->count())->toBe(2)
        ->and(DB::table('users')->where('id', 1)->value('confirmed_at'))->toBe('2026-01-01 12:00:00')
        ->and(DB::table('school_tools')->where('id', 1)->value('material_max_file_upload_size'))->toBe(40960)
        ->and(DB::table('teachers')->count())->toBe(1)
        ->and(Schema::hasIndex('teachers', 'teachers_school_email_index'))->toBeTrue()
        ->and(Schema::hasIndex('teachers', 'teachers_email_index'))->toBeTrue()
        ->and(Schema::hasIndex('teachers', 'teachers_school_sort_index'))->toBeTrue()
        ->and(DB::table('roles')->orderBy('id')->pluck('id')->all())->toBe([3, 4])
        ->and(DB::table('model_has_roles')->orderBy('role_id')->pluck('role_id')->all())->toBe([3, 4])
        ->and(DB::table('role_has_permissions')->orderBy('role_id')->pluck('role_id')->all())->toBe([3, 4])
        ->and(DB::table('permissions')->count())->toBe(1)
        ->and(DB::table('model_has_permissions')->count())->toBe(1)
        ->and(DB::table('licences')->orderBy('id')->pluck('id')->all())->toBe([2, 78])
        ->and(DB::table('school_licences')->pluck('id')->all())->toBe([1])
        ->and(DB::table('school_user_licences')->pluck('id')->all())->toBe([1])
        ->and(DB::table('licence_user_plans')->pluck('id')->all())->toBe([1])
        ->and(Cache::store('array')->has(config('permission.cache.key')))->toBeFalse();
    $licence = DB::table('licences')->where('id', 2)->first();
    $schoolLicence = DB::table('school_licences')->where('id', 1)->first();
    expect(json_decode($licence->admin_role_names, true))->toBe(['teacher'])
        ->and(json_decode($licence->user_role_names, true))->toBe(['student'])
        ->and(json_decode($licence->licence_model, true))->toBe($expectedModel)
        ->and(json_decode($schoolLicence->licence_model, true))->toBe($expectedModel)
        ->and(json_decode($schoolLicence->user_licence_assignments)->{'1'})->toEqual((object) ['teacher' => (object) ['valid_until' => '2027-01-01']])
        ->and(json_decode($schoolLicence->user_licence_assignments)->{'2'})->toEqual(new stdClass)
        ->and(json_decode($schoolLicence->user_licence_assignments)->legacy_note)->toBe('tutoring_user')
        ->and(DB::table('licences')->where('id', 78)->value('licence_model'))->toBe('{"untouched":"tutoring_user"}');
});
