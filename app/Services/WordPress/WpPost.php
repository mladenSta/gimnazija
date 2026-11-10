<?php

namespace App\Services\WordPress;

/**
 * A row from the WordPress posts table, typed.
 */
final readonly class WpPost
{
    public function __construct(
        public int $id,
        public string $title,
        public string $name,
        public string $content,
        public string $excerpt,
        public string $status,
        public string $date,
        public string $modified,
    ) {}

    public static function fromRow(object $row): self
    {
        $a = (array) $row;

        return new self(
            id: (int) ($a['ID'] ?? 0),
            title: (string) ($a['post_title'] ?? ''),
            name: (string) ($a['post_name'] ?? ''),
            content: (string) ($a['post_content'] ?? ''),
            excerpt: (string) ($a['post_excerpt'] ?? ''),
            status: (string) ($a['post_status'] ?? ''),
            date: (string) ($a['post_date'] ?? ''),
            modified: (string) ($a['post_modified'] ?? ''),
        );
    }
}
