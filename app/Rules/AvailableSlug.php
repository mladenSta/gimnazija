<?php

namespace App\Rules;

use App\Services\Content\SlugGenerator;
use App\Support\ReservedSlugs;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Valid URL slug, free across posts and pages.
 */
class AvailableSlug implements ValidationRule
{
    public function __construct(private ?Model $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        // Lowercase letters, digits and hyphens. "%" allows URL-encoded slugs imported from WordPress.
        if (! preg_match('/^[a-z0-9%]+(?:-[a-z0-9%]+)*$/', $value)) {
            $fail('Link može sadržati samo mala slova bez kvačica, brojeve i crtice (npr. dan-skole-2026).');

            return;
        }

        if (ReservedSlugs::contains($value)) {
            $fail('Ovaj link je rezervisan za sistem. Izaberite drugi.');

            return;
        }

        if (app(SlugGenerator::class)->isTaken($value, $this->ignore)) {
            $fail('Ovaj link već koristi druga objava ili stranica.');
        }
    }
}
