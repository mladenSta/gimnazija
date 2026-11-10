<?php

namespace App\Models\Concerns;

use App\Enums\ContentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Draft / published / scheduled content.
 *
 * Scheduled = published with published_at in the future. Public queries use
 * published(), which hides scheduled items until their time, so no cron is needed.
 *
 * @property ContentStatus $status
 * @property CarbonImmutable|null $published_at
 */
trait Publishable
{
    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', ContentStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function scheduled(Builder $query): void
    {
        $query->where('status', ContentStatus::Published)
            ->where('published_at', '>', now());
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function drafts(Builder $query): void
    {
        $query->where('status', ContentStatus::Draft);
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }

    public function isScheduled(): bool
    {
        return $this->status === ContentStatus::Published
            && $this->published_at !== null
            && $this->published_at->isFuture();
    }
}
