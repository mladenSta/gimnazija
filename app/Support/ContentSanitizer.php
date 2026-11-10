<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans rich-text HTML from the editor before it is stored.
 * Allowlist only: anything not listed (scripts, styles, event handlers,
 * classes, inline styles) is removed.
 */
class ContentSanitizer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withMaxInputLength(2_000_000);

        foreach (['p', 'br', 'hr', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
            'ul', 'ol', 'li', 'blockquote', 'figure', 'figcaption',
            'table', 'colgroup', 'col', 'thead', 'tbody', 'tfoot', 'tr'] as $element) {
            $config = $config->allowElement($element);
        }

        $config = $config
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height'])
            ->allowElement('th', ['colspan', 'rowspan'])
            ->allowElement('td', ['colspan', 'rowspan'])
            // h1 is reserved for the page title.
            ->allowElement('h1');

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = $this->sanitizer->sanitize($html);
        $clean = preg_replace('#<(/?)h1>#i', '<$1h2>', $clean) ?? $clean;

        // Editors leave empty paragraphs at the start/end (e.g. Tiptap's trailing <p></p>).
        $clean = preg_replace('#^(?:\s*<p>(?:\s|&nbsp;|<br\s*/?>)*</p>)+|(?:<p>(?:\s|&nbsp;|<br\s*/?>)*</p>\s*)+$#i', '', $clean) ?? $clean;

        // Tiptap's empty document.
        return trim(strip_tags($clean, '<img>')) === '' ? null : $clean;
    }
}
