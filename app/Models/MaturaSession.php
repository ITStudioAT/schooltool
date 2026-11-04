<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaturaSession extends Model
{
    protected $fillable = ['school_id', 'schoolyear_id', 'created_by', 'name', 'exam_date', 'waiting_places', 'status', 'station_access_id'];

    protected function casts(): array
    {
        return ['exam_date' => 'date', 'waiting_places' => 'integer'];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(MaturaRoom::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(MaturaStudent::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(MaturaVisit::class);
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(MaturaAccess::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MaturaEvent::class);
    }
}
