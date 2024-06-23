<?php

namespace App\Exceptions;

use Exception;

class PolicyMethodMissingException extends Exception
{
    /**
     * PolicyMethodMissingException constructor.
     *
     * @param  null  $message
     * @param  null  $code
     */
    public function __construct($message = null, $code = null, ?Exception $exception = null) {
        parent::__construct($message ?? 'The method does not exist in the policy.', 0, $exception);

        $this->code = $code ?: 0;
    }
}
