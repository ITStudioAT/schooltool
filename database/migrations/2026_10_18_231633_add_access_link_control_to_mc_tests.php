<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mc_tests', function (Blueprint $table): void {
            $table->timestamp('access_links_disabled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mc_tests', fn (Blueprint $table) => $table->dropColumn('access_links_disabled_at'));
    }
};
