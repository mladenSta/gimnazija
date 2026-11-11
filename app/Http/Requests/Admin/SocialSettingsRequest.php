<?php

namespace App\Http\Requests\Admin;

use App\Enums\SocialNetwork;
use App\Services\Social\SocialAccounts;
use Illuminate\Foundation\Http\FormRequest;

class SocialSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $networks = (array) $this->input('networks', []);

        foreach ($networks as $key => $row) {
            if (is_array($row) && array_key_exists('account_id', $row)) {
                $networks[$key]['account_id'] = SocialAccounts::normalizeId(is_string($row['account_id']) ? $row['account_id'] : null);
            }
        }

        $this->merge(['networks' => $networks]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['networks' => ['required', 'array']];

        foreach (SocialNetwork::cases() as $network) {
            $key = "networks.{$network->value}";
            $rules[$key] = ['sometimes', 'array'];
            $rules["{$key}.account_id"] = ['nullable', 'string', 'max:100', 'regex:/^\d+$/'];
            $rules["{$key}.token"] = ['nullable', 'string', 'max:4000'];
            $rules["{$key}.clear"] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'networks.*.account_id.regex' => 'ID se sastoji samo od cifara.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (SocialNetwork::cases() as $network) {
            $attributes["networks.{$network->value}.account_id"] = $network->idLabel();
            $attributes["networks.{$network->value}.token"] = "{$network->label()} token";
        }

        return $attributes;
    }
}
