<?php

namespace App\Models;

use App\Models\School;
use App\Models\SchoolLicence;
use Illuminate\Database\Eloquent\Model;

class Licence extends Model
{
    protected $guarded = [];

    public function schoolLicences()
    {
        return $this->hasMany(SchoolLicence::class);
    }

    public function schools()
    {
        return $this->hasManyThrough(School::class, SchoolLicence::class);
    }
}
