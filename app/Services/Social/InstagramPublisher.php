<?php

namespace App\Services\Social;

use App\Enums\SocialNetwork;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Photo post on the school's Instagram business account (Graph API):
 * create a media container from a public JPEG URL, wait until Instagram has
 * downloaded it, then publish. Links in captions aren't clickable on
 * Instagram, so the address is added as text at the end.
 */
class InstagramPublisher implements Publisher
{
    private const CAPTION_MAX = 2200;

    public function __construct(private GraphApi $api, private JpegCopy $jpeg) {}

    public function network(): SocialNetwork
    {
        return SocialNetwork::Instagram;
    }

    public function unavailableReason(Post $post): ?string
    {
        return $post->featuredImage === null ? 'Za Instagram je potrebna naslovna slika.' : null;
    }

    public function publish(Post $post, Credentials $credentials): ShareResult
    {
        $media = $post->featuredImage;

        if (! $media instanceof Media) {
            throw new SocialPublishException('Za Instagram je potrebna naslovna slika.');
        }

        $image = $this->jpeg->make($media);
        $link = ShareContent::url($post);
        $caption = mb_substr(ShareContent::text($post, 1500), 0, self::CAPTION_MAX - mb_strlen($link) - 4)."\n\n".$link;

        $container = (string) ($this->api->post("{$credentials->accountId}/media", [
            'image_url' => $image['url'],
            'caption' => $caption,
        ], $credentials->token)['id'] ?? '');

        if ($container === '') {
            throw new SocialPublishException('Instagram nije prihvatio fotografiju.');
        }

        $this->waitUntilReady($container, $credentials->token);

        $id = (string) ($this->api->post("{$credentials->accountId}/media_publish", [
            'creation_id' => $container,
        ], $credentials->token)['id'] ?? '');

        if ($id === '') {
            throw new SocialPublishException('Instagram nije vratio ID objave.');
        }

        return new ShareResult($id, $this->permalink($id, $credentials->token));
    }

    /**
     * @throws SocialPublishException
     */
    private function waitUntilReady(string $container, string $token): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $status = (string) ($this->api->get($container, ['fields' => 'status_code'], $token)['status_code'] ?? '');

            if ($status === 'FINISHED') {
                return;
            }

            if (in_array($status, ['ERROR', 'EXPIRED'], true)) {
                throw new SocialPublishException('Instagram nije mogao obraditi fotografiju (mora biti javno dostupna na internetu, JPEG).');
            }

            Sleep::for(2)->seconds();
        }

        throw new SocialPublishException('Instagram još obrađuje fotografiju. Pokušajte ponovo za minut.');
    }

    private function permalink(string $id, string $token): ?string
    {
        try {
            $url = $this->api->get($id, ['fields' => 'permalink'], $token)['permalink'] ?? null;

            return is_string($url) ? $url : null;
        } catch (Throwable) {
            return null; // Published anyway; the link is a nice-to-have.
        }
    }
}
