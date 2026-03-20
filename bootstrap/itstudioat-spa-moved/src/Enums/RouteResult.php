<?php

namespace App\Enums;

enum RouteResult: string
{
    case ALLOWED = 'allowed'; // Route ist erlaubt
    case NOT_ALLOWED = 'not_allowed'; // Route ist nicht erlaubt
    case NOT_EXISTS = 'not_exists'; // Diese Route existiert überhaupt nicht
    case NOT_FOUND = 'not_found'; // Diese Rout existiert zwar grundsätzlich, ist aber unter routes/meta nicht definiert
}
