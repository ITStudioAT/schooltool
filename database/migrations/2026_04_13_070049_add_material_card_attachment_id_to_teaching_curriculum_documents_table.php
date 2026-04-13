<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_curriculum_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('teaching_curriculum_documents', 'material_card_attachment_id')) {
                $table->unsignedBigInteger('material_card_attachment_id')->nullable()->after('material_card_id');
                $table->foreign('material_card_attachment_id', 'tcd_material_attachment_fk')
                    ->references('id')
                    ->on('material_card_attachments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('teaching_curriculum_documents', function (Blueprint $table) {
            if (Schema::hasColumn('teaching_curriculum_documents', 'material_card_attachment_id')) {
                $table->dropForeign('tcd_material_attachment_fk');
                $table->dropColumn('material_card_attachment_id');
            }
        });
    }
};
