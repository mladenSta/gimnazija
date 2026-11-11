<?php

namespace App\Services\Social;

/**
 * Account ID + access token of one network, as entered in Podešavanja.
 */
final readonly class Credentials
{
    public function __construct(
        public string $accountId,
        public string $token,
    ) {}
}
