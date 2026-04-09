<?php

namespace App\Actions\Concerns;

use Illuminate\Validation\ValidationException;
use Recurr\Rule;

trait ValidatesRecurrenceRule
{
    protected function validateRecurrenceRule(string $rule): void
    {
        try {
            new Rule($rule);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'recurrence_rule' => ['The recurrence rule is invalid: ' . $e->getMessage()],
            ]);
        }
    }
}
