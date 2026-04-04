<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('restaurant_billings')) {
            return;
        }

        Schema::create('restaurant_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('weeks_count')->default(1);
            $table->unsignedInteger('bookings_count')->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['school_id', 'start_date', 'end_date'], 'restaurant_billings_school_period_unique');
            $table->index(['school_id', 'created_at'], 'restaurant_billings_school_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_billings');
    }
};
