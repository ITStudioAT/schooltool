<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use App\Traits\HasRoleTrait;

class Controller extends BaseController
{
    use HasRoleTrait;
}
