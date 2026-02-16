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
        if (Schema::hasIndex('users', 'users_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });
        }

        if (Schema::hasIndex('users', 'users_school_id_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_school_id_email_unique');
            });
        }

        if (! Schema::hasIndex('users', 'users_email_school_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique(['email', 'school_id'], 'users_email_school_id_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('users', 'users_email_school_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_school_id_unique');
            });
        }

        if (! Schema::hasIndex('users', 'users_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });
        }
    }
};
