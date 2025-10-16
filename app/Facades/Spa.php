<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Spa
 */
class Spa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Spa::class;
    }
}
