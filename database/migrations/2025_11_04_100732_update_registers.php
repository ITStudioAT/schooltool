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
        if (! Schema::hasTable('registers')) {
            return;
        }

        $columns = Schema::getColumnListing('registers');

        Schema::table('registers', function (Blueprint $table) use ($columns) {
            if (! in_array('show_note', $columns, true)) {
                $table->boolean('show_note')->default(1);
            }
            if (! in_array('must_note', $columns, true)) {
                $table->boolean('must_note')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('registers')) {
            return;
        }

        $columns = Schema::getColumnListing('registers');
        $droppables = array_intersect($columns, ['show_note', 'must_note']);

        if (! empty($droppables)) {
            Schema::table('registers', function (Blueprint $table) use ($droppables) {
                $table->dropColumn($droppables);
            });
        }
    }
};
