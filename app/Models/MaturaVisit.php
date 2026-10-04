<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaturaVisit extends Model
{
    public const Active = ['requested', 'approved', 'departed', 'arrived', 'toilet', 'returning'];

    public const Reserved = ['approved', 'departed', 'arrived', 'toilet'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime', 'approved_at' => 'datetime', 'departed_at' => 'datetime',
            'arrived_at' => 'datetime', 'entered_at' => 'datetime', 'exited_at' => 'datetime',
            'returned_at' => 'datetime', 'cancelled_at' => 'datetime', 'voided_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(MaturaStudent::class, 'matura_student_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(MaturaRoom::class, 'matura_room_id');
    }
}
