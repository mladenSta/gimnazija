<?php

namespace App\Services\WordPress;

use App\Support\ContentSanitizer;

/**
 * Turns WordPress (block editor) post HTML into the new site's format:
 *   - text stays (paragraphs, lists, headings, quotes, tables, links)
 *   - image and gallery blocks are removed from the text; their photos
 *     become the post's gallery below the text (in order)
 *   - video / embeds are removed (no video on the new site)
 *   - file blocks become a plain link
 *   - links to the old domain become relative (/slug/, /wp-content/…)
 */
class ContentConverter
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private AttachmentIndex $attachments,
        private string $oldSiteUrl,
    ) {}

    /**
     * @return array{html: string|null, image_ids: list<int>, removed_embeds: int}
     */
    public function convert(string $html): array
    {
        $imageIds = [];
        $removedEmbeds = 0;

        // 0. Old pages built with the theme's page builder (WPBakery shortcodes).
        if (str_contains($html, '[vc_')) {
            $html = $this->pageBuilder($html, $imageIds, $removedEmbeds);
        }

        // 1. Image and gallery blocks → gallery below the text (document order).
        $html = (string) preg_replace_callback(
            '#<!--\s*wp:(gallery|image)\b.*?<!--\s*/wp:\1\s*-->#s',
            function (array $m) use (&$imageIds) {
                foreach ($this->imageIdsIn($m[0]) as $id) {
                    $imageIds[] = $id;
                }

                return '';
            },
            $html,
        );

        // 2. Video and embeds (YouTube, Facebook…): removed.
        $html = (string) preg_replace_callback(
            '#<!--\s*wp:(embed|video|core-embed/[a-z0-9-]+)\b.*?<!--\s*/wp:\1\s*-->#s',
            function () use (&$removedEmbeds) {
                $removedEmbeds++;

                return '';
            },
            $html,
        );
        $html = (string) preg_replace_callback('#<iframe\b.*?</iframe>#is', function () use (&$removedEmbeds) {
            $removedEmbeds++;

            return '';
        }, $html);

        // 3. File blocks: keep one plain link (drop the duplicate "Preuzmi" button).
        $html = (string) preg_replace_callback(
            '#<!--\s*wp:file\b.*?<!--\s*/wp:file\s*-->#s',
            function (array $m) {
                if (! preg_match('#<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', $m[0], $a)) {
                    return '';
                }

                $label = trim(strip_tags($a[2])) ?: basename(rawurldecode($a[1]));

                return '<p><a href="'.$a[1].'">'.htmlspecialchars($label, ENT_QUOTES).'</a></p>';
            },
            $html,
        );

        // 4. Any other <img> left in the text (HTML blocks, old posts) → gallery.
        $html = (string) preg_replace_callback('#<img\b[^>]*>#i', function (array $m) use (&$imageIds) {
            foreach ($this->imageIdsIn($m[0]) as $id) {
                $imageIds[] = $id;
            }

            return '';
        }, $html);

        // 5. Block comments and empty figures left behind.
        $html = (string) preg_replace('#<!--.*?-->#s', '', $html);
        $html = (string) preg_replace('#<figure\b[^>]*>\s*(<figcaption\b[^>]*>.*?</figcaption>)?\s*</figure>#is', '', $html);

        // 6. Old-domain links → relative.
        $html = $this->relativeLinks($html);

        return [
            'html' => $this->sanitizer->clean($html),
            'image_ids' => array_values(array_unique($imageIds)),
            'removed_embeds' => $removedEmbeds,
        ];
    }

    /**
     * WPBakery page builder → plain HTML:
     *   - [vc_single_image image="ID"] → gallery photo
     *   - videos, contact forms, decorative headings/separators → removed
     *   - the theme's contact box → a paragraph with address, email, phone
     *   - layout shortcodes (rows, columns, text boxes) → dropped, text kept
     *   - text typed without <p> (one paragraph per line, "* " lists) → HTML
     *
     * @param  list<int>  $imageIds
     */
    private function pageBuilder(string $html, array &$imageIds, int &$removedEmbeds): string
    {
        $html = (string) preg_replace_callback('#\[vc_single_image\b[^\]]*\]#', function (array $m) use (&$imageIds) {
            if (preg_match('#\simage="(\d+)"#', $m[0], $id)) {
                $imageIds[] = (int) $id[1];
            }

            return "\n";
        }, $html);

        $html = (string) preg_replace_callback('#\[vc_video\b[^\]]*\]#', function () use (&$removedEmbeds) {
            $removedEmbeds++;

            return "\n";
        }, $html);

        // Hard line breaks from text pasted out of a PDF ("obrazovno-<br>vaspitnu"):
        // join them unless the line ends a sentence.
        $html = (string) preg_replace('#-<br\s*/?>\s*#u', '-', $html);
        $html = (string) preg_replace('#(?<=[\p{L},])<br\s*/?>\s*#u', ' ', $html);

        $html = (string) preg_replace_callback('#\[eikra-vc-contact\b([^\]]*)\]#', function (array $m) {
            preg_match_all('#(\w+)="([^"]*)"#', $m[1], $pairs, PREG_SET_ORDER);
            $a = array_column($pairs, 2, 1);
            $lines = [];
            if (filled($a['address'] ?? null)) {
                $lines[] = 'Adresa: '.e($a['address']);
            }
            if (filled($a['phone'] ?? null)) {
                $lines[] = 'Telefon: <a href="tel:'.preg_replace('/[^\d+]/', '', $a['phone']).'">'.e($a['phone']).'</a>';
            }
            if (filled($a['email'] ?? null)) {
                $lines[] = 'Email: <a href="mailto:'.e($a['email']).'">'.e($a['email']).'</a>';
            }

            return "\n".($lines ? '<p>'.implode('<br>', $lines).'</p>' : '')."\n";
        }, $html);

        // Everything else in [brackets] that looks like a shortcode: forms,
        // decorative elements and layout wrappers. Only the tags go; any text
        // between an opening and closing tag stays.
        $html = (string) preg_replace('#\[/?(?:vc_[a-z_]+|contact-form-7|eikra-[a-z-]+)\b[^\]]*\]#', "\n", $html);

        return $this->autop($html);
    }

    /**
     * Wrap bare text lines in <p>; consecutive "* item" lines become a list.
     * Lines that already start with a block tag are left alone.
     */
    private function autop(string $html): string
    {
        $out = [];
        $list = [];

        foreach (preg_split('/\R/u', $html) ?: [] as $line) {
            // Leftover block-editor markers ("<!-- /wp:paragraph -->") at the start of a line.
            $line = trim((string) preg_replace('#^(?:\s*<!--\s*/?wp:(?!image|gallery|embed|video|file|core-embed)[^>]*-->)+#', '', $line));

            if ($line === '') {
                continue;
            }

            if (preg_match('/^[*•]\s+(.+)$/u', $line, $m)) {
                $list[] = $m[1];

                continue;
            }

            if ($list !== []) {
                $out[] = '<ul><li>'.implode('</li><li>', $list).'</li></ul>';
                $list = [];
            }

            $out[] = preg_match('#^<(?:/?(?:p|h[1-6]|ul|ol|li|table|thead|tbody|tr|td|th|blockquote|div|figure|figcaption)\b|!--)#i', $line)
                ? $line
                : "<p>{$line}</p>";
        }

        if ($list !== []) {
            $out[] = '<ul><li>'.implode('</li><li>', $list).'</li></ul>';
        }

        return implode("\n", $out);
    }

    public function relativeLinks(string $html): string
    {
        $host = (string) preg_replace('/^www\./', '', (string) parse_url($this->oldSiteUrl, PHP_URL_HOST));

        return (string) preg_replace('#https?://(?:www\.)?'.preg_quote($host, '#').'(?=/|"|\')#i', '', $html);
    }

    /**
     * Attachment ids referenced in a chunk of block HTML, in order.
     *
     * @return list<int>
     */
    private function imageIdsIn(string $chunk): array
    {
        $ids = [];

        // <img … class="wp-image-123"> (most reliable; one per image)
        if (preg_match_all('#<img\b[^>]*>#i', $chunk, $imgs)) {
            foreach ($imgs[0] as $img) {
                if (preg_match('#wp-image-(\d+)#', $img, $m)) {
                    $ids[] = (int) $m[1];
                } elseif (preg_match('#data-id="(\d+)"#', $img, $m)) {
                    $ids[] = (int) $m[1];
                } elseif (preg_match('#\ssrc="([^"]+)"#i', $img, $m) && ($id = $this->attachments->idForUrl(html_entity_decode($m[1])))) {
                    $ids[] = $id;
                }
            }
        }

        // Block JSON without <img> markup: {"id":123} / {"ids":[1,2]}
        if ($ids === [] && preg_match('#"ids":\[([\d,\s]+)\]#', $chunk, $m)) {
            $ids = array_map('intval', array_filter(array_map('trim', explode(',', $m[1]))));
        } elseif ($ids === [] && preg_match('#"id":(\d+)#', $chunk, $m)) {
            $ids[] = (int) $m[1];
        }

        return array_values($ids);
    }
}
