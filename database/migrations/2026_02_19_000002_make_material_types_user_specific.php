<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_types')) {
            return;
        }

        if (! Schema::hasColumn('material_types', 'user_id')) {
            Schema::table('material_types', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('school_id')
                    ->constrained()
                    ->cascadeOnDelete();
            });
        }

        try {
            Schema::table('material_types', function (Blueprint $table) {
                $table->dropUnique(['school_id', 'name']);
            });
        } catch (\Throwable) {
            // ignore if index does not exist
        }

        try {
            Schema::table('material_types', function (Blueprint $table) {
                $table->dropIndex(['school_id', 'name']);
            });
        } catch (\Throwable) {
            // ignore if index does not exist
        }

        $now = now();

        $legacyTypes = DB::table('material_types')
            ->select('id', 'school_id', 'user_id', 'name')
            ->orderBy('id')
            ->get();

        $cardUsers = collect();
        if (Schema::hasTable('material_cards')) {
            $cardUsers = DB::table('material_cards')
                ->select('school_id', 'user_id')
                ->whereNotNull('school_id')
                ->whereNotNull('user_id')
                ->distinct()
                ->get()
                ->filter(function ($row) {
                    return (int) ($row->school_id ?? 0) > 0 && (int) ($row->user_id ?? 0) > 0;
                });
        }

        $usersBySchool = $cardUsers
            ->groupBy('school_id')
            ->map(function ($rows) {
                return $rows
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();
            });

        $validUserIds = $cardUsers
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($legacyTypes as $type) {
            $name = trim((string) ($type->name ?? ''));
            if ($name === '') {
                continue;
            }

            $name = mb_substr($name, 0, 255);
            $schoolId = (int) ($type->school_id ?? 0);
            if ($schoolId <= 0) {
                continue;
            }

            if (! empty($type->user_id)) {
                DB::table('material_types')->updateOrInsert(
                    [
                        'school_id' => $schoolId,
                        'user_id' => (int) $type->user_id,
                        'name' => $name,
                    ],
                    [
                        'updated_at' => $now,
                    ]
                );
                continue;
            }

            $users = $usersBySchool->get($schoolId, collect());
            foreach ($users as $userId) {
                DB::table('material_types')->updateOrInsert(
                    [
                        'school_id' => $schoolId,
                        'user_id' => (int) $userId,
                        'name' => $name,
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        DB::table('material_cards')
            ->select('id', 'school_id', 'user_id', 'type')
            ->whereNotNull('type')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($now): void {
                foreach ($rows as $row) {
                    $name = trim((string) ($row->type ?? ''));
                    $schoolId = (int) ($row->school_id ?? 0);
                    $userId = (int) ($row->user_id ?? 0);

                    if ($name === '' || $schoolId <= 0 || $userId <= 0) {
                        continue;
                    }

                    DB::table('material_types')->updateOrInsert(
                        [
                            'school_id' => $schoolId,
                            'user_id' => $userId,
                            'name' => mb_substr($name, 0, 255),
                        ],
                        [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }, 'id');

        DB::table('material_types')
            ->whereNull('user_id')
            ->delete();

        if (empty($validUserIds)) {
            DB::table('material_types')->delete();
        } else {
            DB::table('material_types')
                ->whereNotIn('user_id', $validUserIds)
                ->delete();
        }

        try {
            Schema::table('material_types', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'name']);
            });
        } catch (\Throwable) {
            // ignore if index does not exist
        }

        try {
            Schema::table('material_types', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'name']);
            });
        } catch (\Throwable) {
            // ignore if index does not exist
        }

        Schema::table('material_types', function (Blueprint $table) {
            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_types')) {
            return;
        }

        if (! Schema::hasColumn('material_types', 'user_id')) {
            return;
        }

        try {
            Schema::table('material_types', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'name']);
            });
        } catch (\Throwable) {
            // ignore if index does not exist
        }

        try {
            Schema::table('material_types', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'name']);
            });
        } catch (\Throwable) {
            // ignore if index does not exist
        }

        $now = now();
        $legacyRows = DB::table('material_types')
            ->select('school_id', 'name')
            ->whereNotNull('school_id')
            ->get()
            ->map(function ($row) {
                $name = trim((string) ($row->name ?? ''));
                if ($name === '') {
                    return null;
                }

                return [
                    'school_id' => (int) $row->school_id,
                    'name' => mb_substr($name, 0, 255),
                ];
            })
            ->filter()
            ->unique(fn ($row) => $row['school_id'] . '|' . mb_strtolower($row['name']))
            ->values();

        DB::table('material_types')->delete();

        foreach ($legacyRows as $row) {
            DB::table('material_types')->insert([
                'school_id' => $row['school_id'],
                'name' => $row['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('material_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('material_types', function (Blueprint $table) {
            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'name']);
        });
    }
};
