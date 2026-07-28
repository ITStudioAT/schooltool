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
        $hasDuplicates = DB::table('restaurant_menu_plan_bookings')
            ->select(['user_id', 'restaurant_menu_plan_entry_id'])
            ->selectRaw('COALESCE(restaurant_eating_time_id, 0) AS eating_time_key')
            ->groupBy([
                'user_id',
                'restaurant_menu_plan_entry_id',
                DB::raw('COALESCE(restaurant_eating_time_id, 0)'),
            ])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Duplicate restaurant bookings must be resolved before adding the booking-slot constraint.');
        }

        Schema::table('restaurant_menu_plan_bookings', function (Blueprint $table) {
            $table->dropForeign('rmp_bookings_time_fk');
        });

        Schema::table('restaurant_menu_plan_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_slot_key')
                ->storedAs('COALESCE(`restaurant_eating_time_id`, 0)')
                ->after('restaurant_eating_time_id');
        });

        Schema::table('restaurant_menu_plan_bookings', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'restaurant_menu_plan_entry_id', 'booking_slot_key'],
                'restaurant_bookings_user_entry_slot_unique',
            );
        });

        Schema::table('restaurant_menu_plan_bookings', function (Blueprint $table) {
            $table->dropUnique('unique_booking_per_time');
            $table->foreign('restaurant_eating_time_id', 'rmp_bookings_time_fk')
                ->references('id')
                ->on('restaurant_eating_times')
                ->restrictOnDelete();
        });
    }
};
