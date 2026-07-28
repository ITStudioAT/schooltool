<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $school_id
 * @property string $flow_uuid
 * @property string $status
 * @property string|null $entry_point
 * @property string|null $account_holder_name
 * @property string|null $address_line
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $country
 * @property string|null $iban
 * @property string|null $bic
 * @property array<int, array{name:string, schoolclass:string}>|null $child_entries
 * @property string|null $sepa_payee_snapshot
 * @property string|null $sepa_mandate_text_snapshot
 * @property Carbon|null $accepted_at
 * @property string|null $accepted_ip
 * @property string|null $confirmation_code
 * @property Carbon|null $confirmation_code_expires_at
 * @property Carbon|null $code_sent_at
 * @property string|null $code_sent_ip
 * @property Carbon|null $confirmed_at
 * @property string|null $confirmed_ip
 * @property Carbon|null $completed_at
 * @property string|null $completed_ip
 */
class RestaurantSepaMandate extends Model
{
    protected $hidden = [
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
        'confirmation_code',
        'confirmation_code_expires_at',
        'code_sent_ip',
        'confirmed_ip',
        'completed_ip',
    ];

    protected $fillable = [
        'user_id',
        'school_id',
        'flow_uuid',
        'status',
        'entry_point',
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
        'accepted_at',
        'accepted_ip',
        'confirmation_code',
        'confirmation_code_expires_at',
        'code_sent_at',
        'code_sent_ip',
        'confirmed_at',
        'confirmed_ip',
        'completed_at',
        'completed_ip',
    ];

    protected function casts(): array
    {
        return [
            'account_holder_name' => 'encrypted',
            'address_line' => 'encrypted',
            'postal_code' => 'encrypted',
            'city' => 'encrypted',
            'country' => 'encrypted',
            'iban' => 'encrypted',
            'bic' => 'encrypted',
            'child_entries' => 'encrypted:array',
            'sepa_payee_snapshot' => 'encrypted',
            'sepa_mandate_text_snapshot' => 'encrypted',
            'accepted_ip' => 'encrypted',
            'code_sent_ip' => 'encrypted',
            'confirmed_ip' => 'encrypted',
            'completed_ip' => 'encrypted',
            'accepted_at' => 'datetime',
            'confirmation_code_expires_at' => 'datetime',
            'code_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
