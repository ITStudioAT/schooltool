<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ENCRYPTED_COLUMNS = [
        'account_holder_name',
        'address_line',
        'postal_code',
        'city',
        'country',
        'iban',
        'bic',
        'child_entries',
        'sepa_payee_snapshot',
        'sepa_mandate_text_snapshot',
        'accepted_ip',
        'code_sent_ip',
        'confirmed_ip',
        'completed_ip',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('restaurant_sepa_mandates')) {
            return;
        }

        DB::table('restaurant_sepa_mandates')
            ->orderBy('id')
            ->chunkById(100, function ($mandates): void {
                foreach ($mandates as $mandate) {
                    $updates = [];

                    foreach (self::ENCRYPTED_COLUMNS as $column) {
                        $value = $mandate->{$column};

                        if ($value !== null && $value !== '') {
                            $updates[$column] = $this->encryptUnlessEncrypted((string) $value);
                        }
                    }

                    if ($mandate->confirmation_code !== null && $mandate->confirmation_code !== '') {
                        $confirmationCode = (string) $mandate->confirmation_code;
                        $updates['confirmation_code'] = password_get_info($confirmationCode)['algo'] !== null
                            ? $confirmationCode
                            : Hash::make($confirmationCode);
                    }

                    if ($updates !== []) {
                        DB::table('restaurant_sepa_mandates')
                            ->where('id', $mandate->id)
                            ->update($updates);
                    }
                }
            });
    }

    private function encryptUnlessEncrypted(string $value): string
    {
        try {
            Crypt::decryptString($value);

            return $value;
        } catch (DecryptException) {
            return Crypt::encryptString($value);
        }
    }
};
