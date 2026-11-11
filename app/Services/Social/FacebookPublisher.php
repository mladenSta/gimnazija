<?php

namespace App\Services\Social;

use App\Enums\SocialNetwork;
use App\Models\Post;

/**
 * Link post on the school's Facebook page. Facebook builds the preview
 * (image, title, description) from the page's Open Graph tags.
 */
class FacebookPublisher implements Publisher
{
    public function __construct(private GraphApi $api) {}

    public function network(): SocialNetwork
    {
        return SocialNetwork::Facebook;
    }

    public function unavailableReason(Post $post): ?string
    {
        return null;
    }

    public function publish(Post $post, Credentials $credentials): ShareResult
    {
        $data = $this->api->post("{$credentials->accountId}/feed", [
            'message' => ShareContent::text($post, 400),
            'link' => ShareContent::url($post),
        ], $credentials->token);

        $id = (string) ($data['id'] ?? '');

        if ($id === '') {
            throw new SocialPublishException('Facebook nije vratio ID objave.');
        }

        return new ShareResult($id, "https://www.facebook.com/{$id}");
    }
}
