<?php

namespace App\Services\Content;

use App\Enums\MenuItemType;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the main menu with the submitted tree (WordPress "Save Menu"):
 * existing items are updated, new ones created, missing ones deleted.
 */
class MenuTreeSaver
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function save(array $items): void
    {
        DB::transaction(function () use ($items) {
            $keep = [];
            $this->saveLevel($items, null, $keep);

            MenuItem::query()->whereNotIn('id', $keep ?: [0])->delete();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<int>  $keep
     */
    private function saveLevel(array $items, ?int $parentId, array &$keep): void
    {
        foreach ($items as $position => $data) {
            $type = MenuItemType::from((string) $data['type']);

            $attributes = [
                'parent_id' => $parentId,
                'sort_order' => $position,
                'type' => $type,
                'page_id' => $type === MenuItemType::Page ? $data['page_id'] : null,
                'category_id' => $type === MenuItemType::Category ? $data['category_id'] : null,
                'url' => $type === MenuItemType::Custom ? ($data['url'] ?? null) : null,
                'label' => filled($data['label'] ?? null) ? trim((string) $data['label']) : null,
            ];

            $id = is_numeric($data['id'] ?? null) ? (int) $data['id'] : null;
            $item = ($id !== null ? MenuItem::query()->whereKey($id)->first() : null) ?? new MenuItem;
            $item->fill($attributes)->save();

            $keep[] = $item->id;

            /** @var list<array<string, mixed>> $children */
            $children = array_values((array) ($data['children'] ?? []));
            $this->saveLevel($children, $item->id, $keep);
        }
    }
}
