<?php

namespace App\Rules;

use App\Domain\Recurrence\RecurrenceRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class ValidRecurrenceRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            RecurrenceRule::validate($value);
        } catch (InvalidArgumentException $e) {
            $fail($e->getMessage());
        }
    }
}