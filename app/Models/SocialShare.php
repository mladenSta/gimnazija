<?php

namespace App\Models;

use App\Enums\SocialNetwork;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "Objavi na …" attempt: what was sent where and what the network answered.
 *
 * @property int $id
 * @property int $post_id
 * @property int|null $user_id
 * @property SocialNetwork $network
 * @property bool $success
 * @property string|null $external_id
 * @property string|null $external_url
 * @property string $message
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['post_id', 'user_id', 'network', 'success', 'external_id', 'external_url', 'message'])]
class SocialShare extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'network' => SocialNetwork::class,
            'success' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
