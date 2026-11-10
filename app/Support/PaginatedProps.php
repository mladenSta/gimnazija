<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shapes a paginator like an API resource collection ({data, meta}) so every
 * admin list uses the same format as the Pagination component expects.
 */
class PaginatedProps
{
    /**
     * @template T
     *
     * @param  LengthAwarePaginator<int, T>  $paginator
     * @return array{data: list<T>, meta: array<string, mixed>}
     */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => array_values($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'links' => $paginator->linkCollection()->toArray(),
            ],
        ];
    }
}
