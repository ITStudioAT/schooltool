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
        $hasDuplicates = DB::table('register_date_bookings')
            ->select(['register_date_id', 'user_id'])
            ->groupBy(['register_date_id', 'user_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Duplicate register bookings must be resolved before adding the unique constraint.');
        }

        $hasOrphanedUsers = DB::table('register_date_bookings')
            ->leftJoin('users', 'users.id', '=', 'register_date_bookings.user_id')
            ->whereNull('users.id')
            ->exists();

        if ($hasOrphanedUsers) {
            throw new RuntimeException('Orphaned register bookings must be resolved before adding the user foreign key.');
        }

        Schema::table('register_date_bookings', function (Blueprint $table) {
            $table->unique(
                ['register_date_id', 'user_id'],
                'register_date_bookings_date_user_unique',
            );
            $table->foreign('user_id', 'register_date_bookings_user_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }
};
