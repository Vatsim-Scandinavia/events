<?php

namespace App\Exceptions;

use Exception;

class PolicyMissingException extends Exception
{
    /**
     * PolicyDoesNotExistException constructor.
     *
     * @param  null  $message
     * @param  null  $code
     */
    public function __construct($message, $code, ?Exception $exception)
    {
        parent::__construct($message ?? 'A policy does not exist for this model.', 0, $exception);

        $this->code = $code ?: 0;
    }
}
