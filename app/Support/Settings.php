<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Admin-entered settings (settings table, values encrypted with APP_KEY).
 *
 * Values are loaded once per request. A value that can no longer be decrypted
 * (APP_KEY changed) is treated as empty, so the admin just enters it again.
 */
class Settings
{
    /** @var array<string, string>|null */
    private ?array $values = null;

    public function get(string $key): ?string
    {
        $value = $this->all()[$key] ?? null;

        return filled($value) ? $value : null;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Save several values at once. Null or '' removes the key.
     *
     * @param  array<string, string|null>  $values
     */
    public function put(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                if (blank($value)) {
                    Setting::query()->where('key', $key)->delete();
                } else {
                    Setting::query()->updateOrCreate(['key' => $key], ['value' => Crypt::encryptString($value)]);
                }
            }
        });

        $this->values = null;
    }

    /**
     * @return array<string, string>
     */
    private function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $this->values = [];

        foreach (Setting::query()->get() as $setting) {
            try {
                $this->values[$setting->key] = Crypt::decryptString((string) $setting->value);
            } catch (DecryptException) {
                // Unreadable after an APP_KEY change: treat as not set.
            }
        }

        return $this->values;
    }
}
