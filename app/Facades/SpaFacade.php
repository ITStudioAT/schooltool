<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Spa  // change this if your underlying class is different
 */
class SpaFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Spa::class; // or your container binding key / class name
    }
}
