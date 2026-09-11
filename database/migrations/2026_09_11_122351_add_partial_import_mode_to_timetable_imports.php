<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_imports', function (Blueprint $table): void {
            $table->string('import_mode', 16)->default('strict');
            $table->unsignedInteger('tt_imported_rows')->nullable();
        });
    }
};
