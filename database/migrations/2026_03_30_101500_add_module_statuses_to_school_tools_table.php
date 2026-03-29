<?php

use App\Services\SchoolToolModuleStatusService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('school_tools', 'register_status')) {
                $table->string('register_status')->default(SchoolToolModuleStatusService::ACTIVE)->after('active_schoolyear_id');
            }

            if (! Schema::hasColumn('school_tools', 'tutoring_status')) {
                $table->string('tutoring_status')->default(SchoolToolModuleStatusService::INACTIVE)->after('register_status');
            }

            if (! Schema::hasColumn('school_tools', 'teaching_status')) {
                $table->string('teaching_status')->default(SchoolToolModuleStatusService::INACTIVE)->after('tutoring_status');
            }

            if (! Schema::hasColumn('school_tools', 'materials_status')) {
                $table->string('materials_status')->default(SchoolToolModuleStatusService::INACTIVE)->after('teaching_status');
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_status')) {
                $table->string('restaurant_status')->default(SchoolToolModuleStatusService::INACTIVE)->after('materials_status');
            }
        });

        $service = app(SchoolToolModuleStatusService::class);
        $defaults = $service->defaultAttributes();

        DB::table('school_tools')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $row) use ($defaults): void {
                DB::table('school_tools')
                    ->where('id', $row->id)
                    ->update([
                        'register_status' => $defaults['register_status'],
                        'tutoring_status' => $defaults['tutoring_status'],
                        'teaching_status' => $defaults['teaching_status'],
                        'materials_status' => $defaults['materials_status'],
                        'restaurant_status' => $defaults['restaurant_status'],
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            $columns = collect([
                'register_status',
                'tutoring_status',
                'teaching_status',
                'materials_status',
                'restaurant_status',
            ])->filter(fn (string $column): bool => Schema::hasColumn('school_tools', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
