<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->string('label');
            $table->timestamps();

            $table->unique(['school_id', 'value']);
            $table->index(['school_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_statuses');
    }
};
