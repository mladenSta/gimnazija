<?php

namespace App\Rules;

use App\Support\YouTube;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class YouTubeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || YouTube::id($value) === null) {
            $fail('Polje :attribute mora biti link na YouTube video (npr. https://www.youtube.com/watch?v=… ili https://youtu.be/…).');
        }
    }
}
