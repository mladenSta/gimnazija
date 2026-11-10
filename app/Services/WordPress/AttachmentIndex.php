<?php

namespace App\Services\WordPress;

use Illuminate\Database\ConnectionInterface;

/**
 * WordPress image attachments, loaded once: id → file, metadata, alt, caption.
 */
class AttachmentIndex
{
    /** @var array<int, array{file: string, meta: array<string, mixed>, alt: string|null, caption: string|null, mime: string, title: string}> */
    private array $byId = [];

    /** @var array<string, int> "2021/03/photo.jpg" → id */
    private array $byPath = [];

    private bool $loaded = false;

    public function __construct(private ConnectionInterface $wp) {}

    /**
     * @return array{file: string, meta: array<string, mixed>, alt: string|null, caption: string|null, mime: string, title: string}|null
     */
    public function get(int $id): ?array
    {
        $this->load();

        return $this->byId[$id] ?? null;
    }

    /**
     * Find the attachment behind an image URL (also resized copies like photo-1024x768.jpg).
     */
    public function idForUrl(string $url): ?int
    {
        $this->load();

        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $pos = strpos($path, '/wp-content/uploads/');

        if ($pos === false) {
            return null;
        }

        $relative = substr($path, $pos + strlen('/wp-content/uploads/'));
        $original = (string) preg_replace('#-\d+x\d+(?=\.[a-z0-9]+$)#i', '', $relative);

        foreach ([$relative, $original, (string) preg_replace('#(\.[a-z0-9]+)$#i', '-scaled$1', $original)] as $candidate) {
            if (isset($this->byPath[$candidate])) {
                return $this->byPath[$candidate];
            }
        }

        return null;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        $posts = $this->wp->table('posts')
            ->where('post_type', 'attachment')
            ->where('post_mime_type', 'like', 'image/%')
            ->get(['ID', 'post_excerpt', 'post_mime_type', 'post_title']);

        $meta = $this->wp->table('postmeta')
            ->whereIn('meta_key', ['_wp_attached_file', '_wp_attachment_metadata', '_wp_attachment_image_alt'])
            ->whereIn('post_id', $posts->pluck('ID'))
            ->get(['post_id', 'meta_key', 'meta_value'])
            ->groupBy('post_id');

        foreach ($posts as $post) {
            $values = ($meta[$post->ID] ?? collect())->pluck('meta_value', 'meta_key');
            $file = (string) ($values['_wp_attached_file'] ?? '');

            if ($file === '') {
                continue;
            }

            $data = @unserialize((string) ($values['_wp_attachment_metadata'] ?? ''), ['allowed_classes' => false]);

            $this->byId[(int) $post->ID] = [
                'file' => $file,
                'meta' => is_array($data) ? $data : [],
                'alt' => filled($values['_wp_attachment_image_alt'] ?? null) ? trim((string) $values['_wp_attachment_image_alt']) : null,
                'caption' => filled($post->post_excerpt) ? trim(strip_tags((string) $post->post_excerpt)) : null,
                'mime' => (string) $post->post_mime_type,
                'title' => (string) $post->post_title,
            ];
            $this->byPath[$file] = (int) $post->ID;
        }
    }
}
