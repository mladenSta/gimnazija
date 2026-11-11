<?php

namespace App\Services\Social;

use App\Enums\SocialNetwork;
use App\Support\Settings;

/**
 * Which networks have credentials, and saving them from the settings form.
 */
class SocialAccounts
{
    public function __construct(private Settings $settings) {}

    public function credentials(SocialNetwork $network): ?Credentials
    {
        $keys = $network->settingKeys();
        $id = $this->settings->get($keys['id']);
        $token = $this->settings->get($keys['token']);

        return $id !== null && $token !== null ? new Credentials($id, $token) : null;
    }

    public function isConfigured(SocialNetwork $network): bool
    {
        return $this->credentials($network) !== null;
    }

    /**
     * @return list<SocialNetwork>
     */
    public function configured(): array
    {
        return array_values(array_filter(SocialNetwork::cases(), fn (SocialNetwork $n) => $this->isConfigured($n)));
    }

    /**
     * Form data for the settings page. Tokens never go back to the browser,
     * only whether one is saved and its last 4 characters.
     *
     * @return list<array<string, mixed>>
     */
    public function forForm(): array
    {
        return array_map(function (SocialNetwork $network) {
            $keys = $network->settingKeys();
            $token = $this->settings->get($keys['token']);

            return [
                'key' => $network->value,
                'label' => $network->label(),
                'id_label' => $network->idLabel(),
                'account_id' => $this->settings->get($keys['id']),
                'has_token' => $token !== null,
                'token_hint' => $token !== null ? '…'.mb_substr($token, -4) : null,
                'configured' => $this->isConfigured($network),
            ];
        }, SocialNetwork::cases());
    }

    /**
     * @param  array<string, array{account_id?: string|null, token?: string|null, clear?: bool}>  $input
     */
    public function save(array $input): void
    {
        $values = [];

        foreach (SocialNetwork::cases() as $network) {
            $row = $input[$network->value] ?? null;

            if ($row === null) {
                continue;
            }

            $keys = $network->settingKeys();

            if ($row['clear'] ?? false) {
                $values[$keys['id']] = null;
                $values[$keys['token']] = null;

                continue;
            }

            $values[$keys['id']] = self::normalizeId($row['account_id'] ?? null);

            // Empty token field = keep the saved one.
            if (filled($row['token'] ?? null)) {
                $values[$keys['token']] = trim((string) $row['token']);
            }
        }

        $this->settings->put($values);
    }

    /**
     * "urn:li:organization:123", "123 " → "123".
     */
    public static function normalizeId(?string $id): ?string
    {
        $id = trim((string) $id);
        $id = (string) preg_replace('/^urn:li:(organization|company):/i', '', $id);

        return $id !== '' ? $id : null;
    }
}
