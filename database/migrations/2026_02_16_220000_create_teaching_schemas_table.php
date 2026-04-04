<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teaching_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->index();
            $table->foreignId('schoolyear_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('schema_id', 36);
            $table->string('name', 255);
            $table->json('works')->nullable();
            $table->json('grading')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'schoolyear_id', 'schema_id'], 'teaching_schemas_user_year_schema_unique');
            $table->index(['school_id', 'schoolyear_id'], 'teaching_schemas_school_year_idx');
        });

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'teaching_schemas')) {
            return;
        }

        $now = now();

        DB::table('users')
            ->select(['id', 'school_id', 'schoolyear_id', 'teaching_schemas'])
            ->whereNotNull('teaching_schemas')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($now) {
                foreach ($users as $user) {
                    $rawSchemas = $user->teaching_schemas;
                    if (is_string($rawSchemas)) {
                        $decoded = json_decode($rawSchemas, true);
                        $schemas = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
                    } elseif (is_array($rawSchemas)) {
                        $schemas = $rawSchemas;
                    } else {
                        $schemas = [];
                    }

                    if (! is_array($schemas) || empty($schemas)) {
                        continue;
                    }

                    foreach ($schemas as $schema) {
                        if (! is_array($schema)) {
                            continue;
                        }

                        $schemaId = (string) ($schema['id'] ?? '');
                        if ($schemaId === '') {
                            continue;
                        }

                        $name = (string) ($schema['name'] ?? 'Standard');
                        $works = is_array($schema['works'] ?? null) ? $schema['works'] : [];
                        $grading = is_array($schema['grading'] ?? null) ? $schema['grading'] : [];

                        DB::table('teaching_schemas')->updateOrInsert(
                            [
                                'user_id' => $user->id,
                                'schoolyear_id' => $user->schoolyear_id,
                                'schema_id' => $schemaId,
                            ],
                            [
                                'school_id' => $user->school_id,
                                'name' => $name !== '' ? $name : 'Standard',
                                'works' => json_encode($works),
                                'grading' => json_encode($grading),
                                'updated_at' => $now,
                                'created_at' => $now,
                            ]
                        );
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_schemas');
    }
};
