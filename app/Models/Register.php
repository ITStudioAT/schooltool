<?php

namespace App\Models;

use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Register extends Model
{
    use HasFactory;

    protected $guarded = [];


    protected $casts = [
        'is_active' => 'boolean',
        'show_phone' => 'boolean',
        'must_phone' => 'boolean',
        'show_student_last_name' => 'boolean',
        'must_student_last_name' => 'boolean',
        'show_student_first_name' => 'boolean',
        'must_student_first_name' => 'boolean',
        'show_booked' => 'boolean',
        'show_end_time' => 'boolean',
        'show_supervisor' => 'boolean',
    ];

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }


    public function dates()
    {
        return $this->hasMany(RegisterDate::class);
    }

    public function bookings()
    {
        // goes through register_dates → register_date_bookings
        return $this->hasManyThrough(
            RegisterDateBooking::class, // final model
            RegisterDate::class,        // intermediate
            'register_id',              // FK on register_dates -> registers.id
            'register_date_id',         // FK on register_date_bookings -> register_dates.id
            'id',                       // local key on registers
            'id'                        // local key on register_dates
        );
    }

    public function hasDependencies(): bool
    {
        return false;
    }
}
