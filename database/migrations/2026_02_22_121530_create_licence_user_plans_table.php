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
        Schema::create('licence_user_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licence_id')->constrained('licences')->cascadeOnDelete();
            $table->string('role_name');
            $table->string('text');
            $table->string('price_per_year');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['licence_id', 'role_name']);
        });

        $now = now();
        $rows = [];

        foreach (DB::table('licences')->select(['id', 'licence_model'])->cursor() as $licenceRow) {
            $licenceModel = $licenceRow->licence_model;
            if (is_string($licenceModel)) {
                $licenceModel = json_decode($licenceModel, true);
            }

            if (! is_array($licenceModel)) {
                continue;
            }

            $plansByRole = is_array($licenceModel['user_licence_plans_by_role'] ?? null)
                ? $licenceModel['user_licence_plans_by_role']
                : [];
            $requiredByRole = is_array($licenceModel['user_licence_required_by_role'] ?? null)
                ? $licenceModel['user_licence_required_by_role']
                : [];

            $roleNames = collect(array_keys($plansByRole))
                ->merge(array_keys($requiredByRole))
                ->filter(fn ($roleName) => is_string($roleName) && trim($roleName) !== '')
                ->unique()
                ->values();

            foreach ($roleNames as $roleName) {
                $plans = $plansByRole[$roleName] ?? [];
                if (! is_array($plans)) {
                    $plans = [];
                }

                $roleName = trim((string) $roleName);
                if ($roleName === '') {
                    continue;
                }

                $sortOrder = 0;
                $insertedForRole = 0;
                foreach ($plans as $plan) {
                    if (! is_array($plan)) {
                        continue;
                    }

                    $text = trim((string) ($plan['text'] ?? ''));
                    $pricePerYear = trim((string) ($plan['price_per_year'] ?? ''));
                    if ($text === '' || $pricePerYear === '') {
                        continue;
                    }

                    $rows[] = [
                        'licence_id' => (int) $licenceRow->id,
                        'role_name' => $roleName,
                        'text' => mb_substr($text, 0, 255),
                        'price_per_year' => mb_substr($pricePerYear, 0, 255),
                        'sort_order' => $sortOrder++,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $insertedForRole++;

                    if (count($rows) >= 500) {
                        DB::table('licence_user_plans')->insert($rows);
                        $rows = [];
                    }
                }

                $isRequired = filter_var($requiredByRole[$roleName] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($isRequired === null) {
                    $isRequired = in_array(strtolower(trim((string) ($requiredByRole[$roleName] ?? ''))), ['1', 'true', 'yes', 'ja'], true);
                }

                if ($isRequired && $insertedForRole === 0) {
                    $rows[] = [
                        'licence_id' => (int) $licenceRow->id,
                        'role_name' => $roleName,
                        'text' => 'Standard',
                        'price_per_year' => '0',
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($rows) >= 500) {
                        DB::table('licence_user_plans')->insert($rows);
                        $rows = [];
                    }
                }
            }
        }

        if (! empty($rows)) {
            DB::table('licence_user_plans')->insert($rows);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licence_user_plans');
    }
};
