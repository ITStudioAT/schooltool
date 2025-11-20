<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property int $user_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $dispatched_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereDispatchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereUserId($value)
 * @mixin \Eloquent
 * @mixin IdeHelperQueueTest
 */
class QueueTest extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'status',
        'dispatched_at',
        'processed_at'
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
