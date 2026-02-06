<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('teaching_schemas')->nullable();
        });

        Schema::table('teaching_courses', function (Blueprint $table) {
            $table->string('teaching_schema_id', 36)->nullable();
        });

        // Migrate existing data
        $users = DB::table('users')
            ->where(function ($query) {
                $query->whereNotNull('teaching_works')
                    ->orWhereNotNull('teaching_grading');
            })
            ->get();

        foreach ($users as $user) {
            $works = json_decode($user->teaching_works, true) ?? [];
            $grading = json_decode($user->teaching_grading, true) ?? [];

            if (empty($works) && empty($grading)) {
                continue;
            }

            $schemaId = (string) Str::uuid();

            $schemas = [
                [
                    'id' => $schemaId,
                    'name' => 'Standard',
                    'works' => $works,
                    'grading' => $grading,
                ],
            ];

            DB::table('users')
                ->where('id', $user->id)
                ->update(['teaching_schemas' => json_encode($schemas)]);

            DB::table('teaching_courses')
                ->where('user_id', $user->id)
                ->update(['teaching_schema_id' => $schemaId]);
        }
    }

    public function down(): void
    {
        Schema::table('teaching_courses', function (Blueprint $table) {
            $table->dropColumn('teaching_schema_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('teaching_schemas');
        });
    }
};
