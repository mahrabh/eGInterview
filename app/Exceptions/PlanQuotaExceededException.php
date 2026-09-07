<?php

namespace App\Exceptions;

use RuntimeException;

class PlanQuotaExceededException extends RuntimeException
{
    public function __construct(
        string $message = 'Monthly plan quota has been reached.',
        public readonly string $module = 'recruitment',
    ) {
        parent::__construct($message);
    }
}
