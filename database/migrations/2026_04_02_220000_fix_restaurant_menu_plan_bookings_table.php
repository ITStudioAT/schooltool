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
        // First drop the table if it exists (with foreign key constraints)
        Schema::dropIfExists('restaurant_menu_plan_bookings');

        Schema::create('restaurant_menu_plan_bookings', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('restaurant_menu_plan_entry_id')->constrained('restaurant_menu_plan_entries', 'id', 'rmp_bookings_entry_fk')->cascadeOnDelete();
            $table->foreignId('restaurant_eating_time_id')->nullable()->constrained('restaurant_eating_times', 'id', 'rmp_bookings_time_fk')->nullOnDelete();

            // Booking details
            $table->decimal('price', 8, 2)->nullable()->comment('Price at time of booking');
            $table->integer('quantity')->default(1)->comment('Number of menus booked');
            $table->decimal('total_price', 10, 2)->nullable()->comment('price * quantity');

            // Child/person information for import166-parent accounts
            $table->string('child_name')->nullable()->comment('Name of child or person for whom the menu is ordered');
            $table->string('child_type')->nullable()->comment('Type: child, other_person, etc.');
            $table->bigInteger('import116_id')->nullable()->comment('Reference to import116 record if applicable');

            // Metadata
            $table->timestamp('booked_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable()->comment('Additional booking metadata');

            // Indexes
            $table->index(['school_id', 'booked_at'], 'rmp_bookings_school_booked_idx');
            $table->index(['user_id', 'booked_at'], 'rmp_bookings_user_booked_idx');
            $table->index(['restaurant_menu_plan_entry_id', 'booked_at'], 'rmp_bookings_entry_booked_idx');
            $table->unique(['user_id', 'restaurant_menu_plan_entry_id', 'restaurant_eating_time_id', 'booked_at'], 'unique_booking_per_time');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_menu_plan_bookings');
    }
};
