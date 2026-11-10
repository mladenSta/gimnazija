<?php

namespace App\Services\Content;

use App\Enums\GalleryType;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\Tag;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;

/**
 * Saves a gallery's name and its full, ordered list of items with tags.
 */
class GallerySaver
{
    /**
     * @param  array{name: string, items?: list<array<string, mixed>>}  $data
     */
    public function save(Gallery $gallery, array $data): void
    {
        DB::transaction(function () use ($gallery, $data) {
            $gallery->update(['name' => $data['name']]);

            $isSlider = $gallery->type === GalleryType::Slider;
            $keep = [];

            foreach ($data['items'] ?? [] as $position => $row) {
                $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : null;

                $item = ($id !== null ? $gallery->items()->whereKey($id)->first() : null) ?? new GalleryItem(['gallery_id' => $gallery->id]);

                $item->fill([
                    'media_id' => (int) $row['media_id'],
                    'caption' => $this->clean($row['caption'] ?? null),
                    'sort_order' => $position,
                    'focal_x' => (int) $row['focal_x'],
                    'focal_y' => (int) $row['focal_y'],
                    'badge' => $isSlider ? $this->clean($row['badge'] ?? null) : null,
                    'title' => $isSlider ? $this->clean($row['title'] ?? null) : null,
                    'subtitle' => $isSlider ? $this->clean($row['subtitle'] ?? null) : null,
                    'link_label' => $isSlider ? $this->clean($row['link_label'] ?? null) : null,
                    'link_url' => $isSlider ? $this->clean($row['link_url'] ?? null) : null,
                ])->save();

                /** @var list<string> $tags */
                $tags = $row['tags'] ?? [];
                $item->tags()->sync($this->tagIds($tags));

                $keep[] = $item->id;
            }

            $gallery->items()->whereNotIn('id', $keep ?: [0])->delete();

            // Tags no photo uses anymore.
            Tag::query()->whereDoesntHave('galleryItems')->delete();
        });
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    private function tagIds(array $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            $name = trim($name);
            $slug = Slug::make($name);

            if ($name === '' || $slug === '') {
                continue;
            }

            $ids[] = Tag::query()->firstOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        return array_values(array_unique($ids));
    }

    private function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
