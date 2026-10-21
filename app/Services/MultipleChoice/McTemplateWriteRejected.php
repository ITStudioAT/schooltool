<?php

namespace App\Services\MultipleChoice;

use Symfony\Component\HttpKernel\Exception\HttpException;

class McTemplateWriteRejected extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(409, $message);
    }
}
