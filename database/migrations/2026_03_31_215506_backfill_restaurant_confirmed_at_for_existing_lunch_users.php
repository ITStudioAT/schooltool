<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'restaurant_confirmed_at')) {
            return;
        }

        $lunchUserIds = DB::table('users')
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'lunch_user')
            ->whereNull('users.restaurant_confirmed_at')
            ->pluck('users.id');

        if ($lunchUserIds->isEmpty()) {
            return;
        }

        DB::table('users')
            ->whereIn('id', $lunchUserIds->all())
            ->update([
                'restaurant_confirmed_at' => DB::raw('COALESCE(confirmed_at, email_verified_at, created_at, NOW())'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'restaurant_confirmed_at')) {
            return;
        }

        $lunchUserIds = DB::table('users')
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'lunch_user')
            ->pluck('users.id');

        if ($lunchUserIds->isEmpty()) {
            return;
        }

        DB::table('users')
            ->whereIn('id', $lunchUserIds->all())
            ->update([
                'restaurant_confirmed_at' => null,
            ]);
    }
};
