<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aba_attachments', function (Blueprint $table) {
            $table->string('document_kind', 32)->default('additional_document')->after('aba_id');
            $table->string('disk', 32)->default('local')->after('path');
            $table->string('stored_name')->nullable()->after('path');
            $table->foreignId('uploaded_by_user_id')->nullable()->after('size_bytes')->constrained('users')->nullOnDelete();

            $table->index(['aba_id', 'document_kind'], 'aba_attachments_aba_kind_idx');
            $table->index('uploaded_by_user_id', 'aba_attachments_uploaded_by_idx');
        });
    }

    public function down(): void
    {
        Schema::table('aba_attachments', function (Blueprint $table) {
            $table->dropIndex('aba_attachments_aba_kind_idx');
            $table->dropIndex('aba_attachments_uploaded_by_idx');
            $table->dropConstrainedForeignId('uploaded_by_user_id');
            $table->dropColumn(['document_kind', 'disk', 'stored_name']);
        });
    }
};
