<?php

namespace App\Services\Social;

/**
 * Successful publication on a network.
 */
final readonly class ShareResult
{
    public function __construct(
        public string $externalId,
        public ?string $externalUrl = null,
    ) {}
}
