<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class EventException extends Exception
{
    protected $route;

    /**
     * EventException constructor.
     *
     * @param  string  $user
     * @param  string  $message
     * @param  int  $code
     */
    public function __construct($message = '', $code = 500, ?Throwable $previous = null, $route = null) 
    {
        parent::__construct($message, $code, $previous);

        $this->route = $route;
    }

    /**
     * Get the view for the exception.
     *
     * @return string|null
     */
    public function getRoute()
    {
        return $this->route;
    }
}