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
        if (! Schema::hasTable('users')) {
            return;
        }

        $columns = Schema::getColumnListing('users');

        Schema::table('users', function (Blueprint $table) use ($columns) {
            // E-Mail als UNIQUE l”schen
            if (! in_array('school_id', $columns, true)) {
                $table->foreignId('school_id')->after('id');
            }

            // E-Mail plus school_id als UNIQUE setzen
            if (! Schema::hasIndex('users', 'users_school_id_email_unique')) {
                $table->unique(['school_id', 'email']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasIndex('users', 'users_school_id_email_unique')) {
                $table->dropUnique('users_school_id_email_unique');
            }

            if (Schema::hasColumn('users', 'school_id')) {
                $table->dropColumn('school_id');
            }
        });
    }
};
