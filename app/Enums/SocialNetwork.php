<?php

namespace App\Enums;

enum SocialNetwork: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::LinkedIn => 'LinkedIn',
        };
    }

    /**
     * Text of the share button in the post editor.
     */
    public function buttonLabel(): string
    {
        return match ($this) {
            self::Facebook => 'Objavi na FB',
            self::Instagram => 'Objavi na IG',
            self::LinkedIn => 'Objavi na LinkedIn',
        };
    }

    /**
     * Settings keys: [account ID, access token].
     *
     * @return array{id: string, token: string}
     */
    public function settingKeys(): array
    {
        return [
            'id' => "social.{$this->value}.id",
            'token' => "social.{$this->value}.token",
        ];
    }

    /**
     * Label of the account ID field in the settings form.
     */
    public function idLabel(): string
    {
        return match ($this) {
            self::Facebook => 'ID Facebook stranice',
            self::Instagram => 'ID Instagram poslovnog naloga',
            self::LinkedIn => 'ID LinkedIn stranice (organizacije)',
        };
    }
}
