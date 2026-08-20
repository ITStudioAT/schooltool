<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTimetableRememberedTtEntry extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'offer_key_hash',
        'entry_key_hash',
        'offer_key',
        'entry_key',
        'offer_name',
        'offer_schedule_label',
        'entry_date_label',
        'entry_date',
        'entry_schedule_label',
        'entry_time_from',
        'entry_time_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
