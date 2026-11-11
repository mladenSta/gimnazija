<?php

namespace App\Services\Social;

use App\Enums\SocialNetwork;
use App\Models\Post;

interface Publisher
{
    public function network(): SocialNetwork;

    /**
     * Why this post can't be published on this network, or null when it can
     * (credentials are checked separately).
     */
    public function unavailableReason(Post $post): ?string;

    /**
     * @throws SocialPublishException
     */
    public function publish(Post $post, Credentials $credentials): ShareResult;
}
