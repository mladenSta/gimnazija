<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Announcement shown in the home page "Najave" section.
 *
 * @property int $id
 * @property string $title
 * @property string|null $short_description
 * @property CarbonImmutable $date
 * @property string|null $time HH:MM:SS
 * @property string|null $location
 * @property string|null $label
 * @property bool $is_published
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'short_description', 'date', 'time', 'location', 'label', 'is_published'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Published events from today on, soonest first.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('is_published', true)
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->orderBy('time');
    }
}
