<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * @method static Builder|Student newModelQuery()
 * @method static Builder|Student newQuery()
 * @method static Builder|Student query()
 *
 * @mixin \Eloquent
 */
class Student extends User
{
    protected $table = 'users';

    protected static function booted()
    {
        parent::booted();

        static::addGlobalScope('role_student', function (Builder $builder) {
            $builder->role('student');
        });
    }

    public function getMorphClass()
    {
        return User::class;
    }
}
