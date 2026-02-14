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
        // Check if old unique constraint exists and drop it
        $indexExists = DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_email_unique'");

        if (!empty($indexExists)) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });
        }

        // Add composite unique constraint on email + school_id
        Schema::table('users', function (Blueprint $table) {
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
