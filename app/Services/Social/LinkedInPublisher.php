<?php

namespace App\Services\Social;

use App\Enums\SocialNetwork;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Article post on the school's LinkedIn page (Posts API). The featured image
 * is uploaded first as the article thumbnail; if that fails the post still
 * goes out, without the image.
 */
class LinkedInPublisher implements Publisher
{
    public const BASE = 'https://api.linkedin.com/rest';

    public function __construct(private JpegCopy $jpeg) {}

    public function network(): SocialNetwork
    {
        return SocialNetwork::LinkedIn;
    }

    public function unavailableReason(Post $post): ?string
    {
        return null;
    }

    public function publish(Post $post, Credentials $credentials): ShareResult
    {
        $author = "urn:li:organization:{$credentials->accountId}";
        $article = [
            'source' => ShareContent::url($post),
            'title' => mb_substr(ShareContent::title($post), 0, 400),
            'description' => $post->summary(250),
        ];

        $thumbnail = $post->featuredImage instanceof Media
            ? $this->uploadThumbnail($post->featuredImage, $author, $credentials->token)
            : null;

        if ($thumbnail !== null) {
            $article['thumbnail'] = $thumbnail;
        }

        $response = $this->send(fn () => $this->client($credentials->token)->post(self::BASE.'/posts', [
            'author' => $author,
            'commentary' => self::escape(ShareContent::text($post, 600)),
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'content' => ['article' => $article],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ]));

        $id = (string) ($response->header('x-restli-id') ?: $response->json('id', ''));

        if ($id === '') {
            throw new SocialPublishException('LinkedIn nije vratio ID objave.');
        }

        return new ShareResult($id, "https://www.linkedin.com/feed/update/{$id}/");
    }

    /**
     * Commentary uses LinkedIn's "little text format": these characters must
     * be escaped or the text is cut off / rejected.
     */
    public static function escape(string $text): string
    {
        return (string) preg_replace('/([\\\\|{}@\[\]()<>#*_~])/', '\\\\$1', $text);
    }

    private function uploadThumbnail(Media $media, string $author, string $token): ?string
    {
        try {
            $image = $this->jpeg->make($media);
            $init = $this->send(fn () => $this->client($token)->post(self::BASE.'/images?action=initializeUpload', [
                'initializeUploadRequest' => ['owner' => $author],
            ]));

            $uploadUrl = (string) $init->json('value.uploadUrl', '');
            $urn = (string) $init->json('value.image', '');

            if ($uploadUrl === '' || $urn === '') {
                return null;
            }

            $upload = Http::timeout((int) config('cms.social_publishing.timeout'))
                ->withToken($token)
                ->withBody((string) file_get_contents($image['path']), 'image/jpeg')
                ->put($uploadUrl);

            return $upload->successful() ? $urn : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function client(string $token): PendingRequest
    {
        return Http::timeout((int) config('cms.social_publishing.timeout'))
            ->withToken($token)
            ->acceptJson()
            ->withHeaders([
                'LinkedIn-Version' => (string) config('cms.social_publishing.linkedin_version'),
                'X-Restli-Protocol-Version' => '2.0.0',
            ]);
    }

    /**
     * @param  callable(): Response  $call
     *
     * @throws SocialPublishException
     */
    private function send(callable $call): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException) {
            throw new SocialPublishException('Nije moguće povezati se sa LinkedIn serverom. Pokušajte ponovo.');
        }

        if ($response->failed()) {
            $text = trim((string) $response->json('message', ''));
            $hint = match ($response->status()) {
                401 => 'Token je istekao ili nije ispravan — unesite novi u Podešavanjima (LinkedIn tokeni traju 60 dana).',
                403 => 'Token nema dozvolu za objavljivanje na ovoj stranici (potrebno: w_organization_social, admin stranice).',
                426 => 'Verzija LinkedIn API-ja više nije podržana (LINKEDIN_API_VERSION).',
                429 => 'Previše zahtjeva — pokušajte kasnije.',
                default => null,
            };

            throw new SocialPublishException(implode(' ', array_filter([$hint, $text !== '' ? "Odgovor: {$text}" : "HTTP {$response->status()}"])));
        }

        return $response;
    }
}
