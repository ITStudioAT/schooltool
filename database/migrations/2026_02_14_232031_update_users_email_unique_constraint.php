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
        Schema::table('users', function (Blueprint $table) {
            // Drop old unique constraint on email only
            // Try both possible constraint names
            try {
                $table->dropUnique('users_email_unique');
            } catch (\Exception $e) {
                try {
                    $table->dropUnique(['email']);
                } catch (\Exception $e) {
                    // Constraint might not exist or have different name
                    // Continue anyway
                }
            }

            // Add composite unique constraint on email + school_id
            // This allows same email across different schools
            $table->unique(['email', 'school_id'], 'users_email_school_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop composite unique constraint
            $table->dropUnique('users_email_school_id_unique');

            // Restore original unique constraint on email only
            $table->unique('email');
        });
    }
};
