<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutoringSubject extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'email_mentors' => 'array',
        'must_be_accepted' => 'boolean',
    ];

    public function offers()
    {
        return $this->hasMany(TutoringOffer::class, 'subject_id');
    }

    /**
     * Prüfe ob das Subject Angebote hat
     */
    public function hasDependencies()
    {
        return $this->offers()->exists();
    }
}
