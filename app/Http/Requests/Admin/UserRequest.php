<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? ($this->user()?->can('update', $user) ?? false)
            : ($this->user()?->can('create', User::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $editing = $user instanceof User;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editing ? $user->id : null)],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => [$editing ? 'nullable' : 'required', 'string', Password::default(), 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->route('user');

            if (! $user instanceof User || $user->role->value === $this->input('role')) {
                return;
            }

            if ($this->user()?->is($user)) {
                $validator->errors()->add('role', 'Ne možete promijeniti svoju ulogu.');

                return;
            }

            if ($user->isAdmin() && User::query()->where('role', Role::Admin)->count() <= 1) {
                $validator->errors()->add('role', 'Sajt mora imati bar jednog administratora.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ime i prezime',
            'email' => 'email adresa',
            'role' => 'uloga',
            'password' => 'lozinka',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Korisnik sa ovom email adresom već postoji.',
        ];
    }
}
