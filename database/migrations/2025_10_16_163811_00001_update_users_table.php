<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = Schema::getColumnListing('users');

            $droppables = [
                'name',
            ];

            $existing = array_intersect($columns, $droppables);

            if (!empty($existing)) {
                $table->dropColumn($existing);
            }

            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->timestamp('login_at')->nullable();
            $table->string('login_ip')->nullable();
            $table->boolean('is_2fa')->nullable()->default(0);
            $table->string('token_2fa')->nullable();
            $table->timestamp('token_2fa_expires_at')->nullable();
            $table->string('token_2fa_2')->nullable();
            $table->timestamp('token_2fa_2_expires_at')->nullable();
            $table->string('email_2fa')->nullable();
            $table->timestamp('email_2fa_verified_at')->nullable();
            $table->boolean('is_active')->nullable()->default(1);
            $table->timestamp('register_started_at')->nullable();
            $table->string('register_as')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('uuid')->nullable();
            $table->timestamp('uuid_at')->nullable();
        });
    }
};
