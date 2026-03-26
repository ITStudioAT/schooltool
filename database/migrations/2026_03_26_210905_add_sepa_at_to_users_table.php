<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'sepa_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('sepa_at')->nullable()->after('import116_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'sepa_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('sepa_at');
            });
        }
    }
};
