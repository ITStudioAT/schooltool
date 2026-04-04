<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_sepa_mandates')) {
            return;
        }

        Schema::create('restaurant_sepa_mandates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('flow_uuid')->unique();
            $table->string('status', 32)->default('draft')->index();
            $table->string('entry_point', 32)->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('address_line', 500)->nullable();
            $table->string('iban', 64)->nullable();
            $table->string('bic', 64)->nullable();
            $table->json('child_entries')->nullable();
            $table->text('sepa_payee_snapshot')->nullable();
            $table->text('sepa_mandate_text_snapshot')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_ip', 45)->nullable();
            $table->string('confirmation_code', 6)->nullable();
            $table->timestamp('confirmation_code_expires_at')->nullable();
            $table->timestamp('code_sent_at')->nullable();
            $table->string('code_sent_ip', 45)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmed_ip', 45)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('completed_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_sepa_mandates');
    }
};
