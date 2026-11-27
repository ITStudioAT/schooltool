<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkDeviceAnswerKey extends Model
{
    public static function forSchool(int $schoolId): self
    {
        return self::query()->where('school_id', $schoolId)->first()
            ?? self::query()->whereNull('school_id')->firstOrFail();
    }

    protected function casts(): array
    {
        return ['answers' => 'array', 'revision' => 'integer'];
    }
}
