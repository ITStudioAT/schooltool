<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassRepresentativeElection extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'class_name',
        'announced_at',
    ];

    protected function casts(): array
    {
        return [
            'announced_at' => 'datetime',
        ];
    }
}
