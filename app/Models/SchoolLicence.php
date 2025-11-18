<?php

namespace App\Models;

use App\Models\Licence;
use App\Models\School;
use Illuminate\Database\Eloquent\Model;

class SchoolLicence extends Model
{
    protected $guarded = [];

    public function licence()
    {
        return $this->belongsTo(Licence::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
