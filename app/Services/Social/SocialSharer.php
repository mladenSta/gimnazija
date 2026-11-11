<?php

namespace App\Services\Social;

use App\Enums\SocialNetwork;
use App\Models\Post;
use App\Models\SocialShare;
use App\Models\User;
use Throwable;

/**
 * Publishes a post on one network and logs the attempt (success or failure).
 */
class SocialSharer
{
    public function __construct(
        private SocialAccounts $accounts,
        private FacebookPublisher $facebook,
        private InstagramPublisher $instagram,
        private LinkedInPublisher $linkedin,
    ) {}

    public function publisher(SocialNetwork $network): Publisher
    {
        return match ($network) {
            SocialNetwork::Facebook => $this->facebook,
            SocialNetwork::Instagram => $this->instagram,
            SocialNetwork::LinkedIn => $this->linkedin,
        };
    }

    /**
     * Why the button is disabled for this post, or null when it can be shared.
     */
    public function unavailableReason(Post $post, SocialNetwork $network): ?string
    {
        if (! $post->isPublished() || $post->trashed()) {
            return 'Objava mora biti objavljena na sajtu.';
        }

        return $this->publisher($network)->unavailableReason($post);
    }

    public function share(Post $post, SocialNetwork $network, ?User $user): SocialShare
    {
        $log = ['post_id' => $post->id, 'user_id' => $user?->id, 'network' => $network];
        $credentials = $this->accounts->credentials($network);

        try {
            if ($credentials === null) {
                throw new SocialPublishException("{$network->label()} nije podešen (Podešavanja).");
            }

            if (($reason = $this->unavailableReason($post, $network)) !== null) {
                throw new SocialPublishException($reason);
            }

            $result = $this->publisher($network)->publish($post, $credentials);

            return SocialShare::query()->create([
                ...$log,
                'success' => true,
                'external_id' => $result->externalId,
                'external_url' => $result->externalUrl,
                'message' => "Objavljeno na mreži {$network->label()}.",
            ]);
        } catch (SocialPublishException $e) {
            $message = $e->getMessage();
        } catch (Throwable $e) {
            report($e);
            $message = 'Neočekivana greška: '.$e->getMessage();
        }

        return SocialShare::query()->create([
            ...$log,
            'success' => false,
            'message' => "{$network->label()}: {$message}",
        ]);
    }

    /**
     * Data for the share buttons in the post editor: configured networks only.
     *
     * @return list<array<string, mixed>>
     */
    public function panel(Post $post): array
    {
        $networks = $this->accounts->configured();

        if ($networks === []) {
            return [];
        }

        $latest = $post->socialShares()
            ->with('user:id,name')
            ->latest('id')
            ->get()
            ->groupBy(fn (SocialShare $s) => $s->network->value);

        return array_map(function (SocialNetwork $network) use ($post, $latest) {
            $shares = $latest->get($network->value, collect());
            $last = $shares->first();
            $lastSuccess = $shares->firstWhere('success', true);

            return [
                'key' => $network->value,
                'label' => $network->label(),
                'button' => $network->buttonLabel(),
                'disabled_reason' => $this->unavailableReason($post, $network),
                'last' => $last instanceof SocialShare ? [
                    'success' => $last->success,
                    'message' => $last->message,
                    'url' => $last->external_url,
                    'at' => $last->created_at?->format('d.m.Y. H:i'),
                    'by' => $last->user?->name,
                ] : null,
                'shared_at' => $lastSuccess instanceof SocialShare ? $lastSuccess->created_at?->format('d.m.Y. H:i') : null,
                'shared_url' => $lastSuccess instanceof SocialShare ? $lastSuccess->external_url : null,
            ];
        }, $networks);
    }
}
