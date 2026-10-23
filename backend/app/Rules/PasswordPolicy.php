<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class PasswordPolicy implements ValidationRule
{
    public const MIN_LENGTH = 12;

    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (mb_strlen($value) < self::MIN_LENGTH) {
            $fail('The :attribute must be at least '.self::MIN_LENGTH.' characters.');

            return;
        }

        if (mb_strlen($value) > 255) {
            $fail('The :attribute must not be greater than 255 characters.');

            return;
        }

        $classes = 0;
        $classes += preg_match('/[a-z]/', $value) === 1 ? 1 : 0;
        $classes += preg_match('/[A-Z]/', $value) === 1 ? 1 : 0;
        $classes += preg_match('/\d/', $value) === 1 ? 1 : 0;
        $classes += preg_match('/[^A-Za-z0-9]/', $value) === 1 ? 1 : 0;

        if ($classes < 3) {
            $fail('The :attribute must include at least three of: lowercase, uppercase, number, symbol.');
        }
    }
}
