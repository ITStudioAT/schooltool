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
            if (! Schema::hasColumn('school_tools', 'aba_visible_admin')) {
                $table->boolean('aba_visible_admin')->default(true)->after('restaurant_user_comming_soon');
            }

            if (! Schema::hasColumn('school_tools', 'aba_visible_user')) {
                $table->boolean('aba_visible_user')->default(true)->after('aba_visible_admin');
            }

            if (! Schema::hasColumn('school_tools', 'aba_user_test_mode')) {
                $table->boolean('aba_user_test_mode')->default(false)->after('aba_visible_user');
            }

            if (! Schema::hasColumn('school_tools', 'aba_user_comming_soon')) {
                $table->boolean('aba_user_comming_soon')->default(false)->after('aba_user_test_mode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            foreach ([
                'aba_user_comming_soon',
                'aba_user_test_mode',
                'aba_visible_user',
                'aba_visible_admin',
            ] as $column) {
                if (Schema::hasColumn('school_tools', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
