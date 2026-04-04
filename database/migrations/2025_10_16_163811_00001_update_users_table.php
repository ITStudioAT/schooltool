<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $columns = Schema::getColumnListing('users');

        Schema::table('users', function (Blueprint $table) use ($columns) {
            $droppables = [
                'name',
            ];

            $existing = array_intersect($columns, $droppables);

            if (! empty($existing)) {
                $table->dropColumn($existing);
            }

            if (! in_array('last_name', $columns, true)) {
                $table->string('last_name')->nullable();
            }
            if (! in_array('first_name', $columns, true)) {
                $table->string('first_name')->nullable();
            }
            if (! in_array('login_at', $columns, true)) {
                $table->timestamp('login_at')->nullable();
            }
            if (! in_array('login_ip', $columns, true)) {
                $table->string('login_ip')->nullable();
            }
            if (! in_array('is_2fa', $columns, true)) {
                $table->boolean('is_2fa')->nullable()->default(0);
            }
            if (! in_array('token_2fa', $columns, true)) {
                $table->string('token_2fa')->nullable();
            }
            if (! in_array('token_2fa_expires_at', $columns, true)) {
                $table->timestamp('token_2fa_expires_at')->nullable();
            }
            if (! in_array('token_2fa_2', $columns, true)) {
                $table->string('token_2fa_2')->nullable();
            }
            if (! in_array('token_2fa_2_expires_at', $columns, true)) {
                $table->timestamp('token_2fa_2_expires_at')->nullable();
            }
            if (! in_array('email_2fa', $columns, true)) {
                $table->string('email_2fa')->nullable();
            }
            if (! in_array('email_2fa_verified_at', $columns, true)) {
                $table->timestamp('email_2fa_verified_at')->nullable();
            }
            if (! in_array('is_active', $columns, true)) {
                $table->boolean('is_active')->nullable()->default(1);
            }
            if (! in_array('register_started_at', $columns, true)) {
                $table->timestamp('register_started_at')->nullable();
            }
            if (! in_array('register_as', $columns, true)) {
                $table->string('register_as')->nullable();
            }
            if (! in_array('confirmed_at', $columns, true)) {
                $table->timestamp('confirmed_at')->nullable();
            }
            if (! in_array('uuid', $columns, true)) {
                $table->string('uuid')->nullable();
            }
            if (! in_array('uuid_at', $columns, true)) {
                $table->timestamp('uuid_at')->nullable();
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $columns = Schema::getColumnListing('users');

        $droppables = [
            'last_name',
            'first_name',
            'login_at',
            'login_ip',
            'is_2fa',
            'token_2fa',
            'token_2fa_expires_at',
            'token_2fa_2',
            'token_2fa_2_expires_at',
            'email_2fa',
            'email_2fa_verified_at',
            'is_active',
            'register_started_at',
            'register_as',
            'confirmed_at',
            'uuid',
            'uuid_at',
        ];

        $existing = array_intersect($columns, $droppables);

        if (! empty($existing)) {
            Schema::table('users', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
