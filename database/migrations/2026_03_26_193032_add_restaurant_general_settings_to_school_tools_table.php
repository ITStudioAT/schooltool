<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('school_tools', 'restaurant_service_email')) {
                $table->string('restaurant_service_email')->nullable()->after('restaurant_menu_visibility_end_mode');
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_new_users_must_confirm_email')) {
                $table->boolean('restaurant_new_users_must_confirm_email')->default(false)->after('restaurant_service_email');
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_user_information_intro_html')) {
                $table->text('restaurant_user_information_intro_html')->nullable()->after('restaurant_new_users_must_confirm_email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            $columns = collect([
                'restaurant_service_email',
                'restaurant_new_users_must_confirm_email',
                'restaurant_user_information_intro_html',
            ])->filter(fn (string $column): bool => Schema::hasColumn('school_tools', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
