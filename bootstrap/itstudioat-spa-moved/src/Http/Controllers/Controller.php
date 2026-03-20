<?php

namespace App\Http\Controllers;

use App\Traits\HasRoleTrait;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use HasRoleTrait;
}
