<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Applies the configured password policy while allowing an empty value so
 * optional "change password" fields can be left blank.
 */
class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $validator = Validator::make(
            [$attribute => $value],
            [$attribute => [Password::defaults()]],
        );

        if ($validator->fails()) {
            $fail($validator->errors()->first($attribute));
        }
    }
}
