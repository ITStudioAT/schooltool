<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'name'], 'material_workspaces_user_name_unique');
            $table->index(['user_id', 'is_default'], 'material_workspaces_user_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_workspaces');
    }
};
