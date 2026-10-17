<?php

namespace App\Models;

use Database\Factories\NumberSystemGameRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NumberSystemGameRun extends Model
{
    /** @use HasFactory<NumberSystemGameRunFactory> */
    use HasFactory;

    protected $attributes = ['position' => 0, 'revision' => 0, 'attempts' => 0, 'hints' => 0, 'solved' => false];

    protected $fillable = ['session_id', 'user_id', 'position', 'revision', 'draft', 'history', 'feedback',
        'attempts', 'hints', 'solved', 'completed_at', 'team_name', 'team_code', 'puzzles', 'finished_seconds'];

    protected $hidden = ['puzzles', 'team_code'];

    protected function casts(): array
    {
        return ['draft' => 'array', 'history' => 'array', 'feedback' => 'array', 'puzzles' => 'array', 'position' => 'integer',
            'revision' => 'integer', 'attempts' => 'integer', 'hints' => 'integer', 'solved' => 'boolean', 'completed_at' => 'datetime', 'finished_seconds' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(NumberSystemGameSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'number_system_game_members', 'run_id', 'user_id')->withPivot('session_id');
    }
}
