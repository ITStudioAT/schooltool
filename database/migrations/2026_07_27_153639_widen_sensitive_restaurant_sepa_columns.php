<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_sepa_mandates', function (Blueprint $table) {
            $table->text('account_holder_name')->nullable()->change();
            $table->text('address_line')->nullable()->change();
            $table->text('postal_code')->nullable()->change();
            $table->text('city')->nullable()->change();
            $table->text('country')->nullable()->change();
            $table->text('iban')->nullable()->change();
            $table->text('bic')->nullable()->change();
            $table->text('child_entries')->nullable()->change();
            $table->text('accepted_ip')->nullable()->change();
            $table->text('confirmation_code')->nullable()->change();
            $table->text('code_sent_ip')->nullable()->change();
            $table->text('confirmed_ip')->nullable()->change();
            $table->text('completed_ip')->nullable()->change();
        });
    }
};
