<?php

namespace App\Models;

use Database\Factories\TeachingPersonalAppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingPersonalAppointment extends Model
{
    /** @use HasFactory<TeachingPersonalAppointmentFactory> */
    use HasFactory;

    public const KINDS = ['supplier_standby', 'consultation', 'standby', 'lunch_supervision', 'day_care_standby', 'special_assignment', 'break_supervision'];

    protected $fillable = ['school_id', 'schoolyear_id', 'user_id', 'kind', 'title', 'date', 'starts_at', 'ends_at', 'school_hours', 'time_segments', 'repeat_until'];

    protected $casts = ['school_hours' => 'array', 'time_segments' => 'array', 'title_exceptions' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
