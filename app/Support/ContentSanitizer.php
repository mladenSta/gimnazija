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

        // Wrapper elements (WordPress blocks, text pasted from Word/web): drop the
        // tag but keep what is inside, so no text is lost.
        foreach (['div', 'span', 'section', 'article', 'header', 'footer', 'main', 'aside', 'center', 'font', 'small', 'mark', 'abbr', 'cite', 'code', 'pre', 'del', 'ins', 'address', 'time', 'label'] as $wrapper) {
            $config = $config->blockElement($wrapper);
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = $this->sanitizer->sanitize($html);
        $clean = preg_replace('#<(/?)h1>#i', '<$1h2>', $clean) ?? $clean;

        // Empty paragraphs (Tiptap's trailing <p></p>, spacer paragraphs from WordPress).
        $clean = preg_replace('#<p>(?:\s|&nbsp;|\x{00A0}|<br\s*/?>)*</p>#iu', '', $clean) ?? $clean;
        $clean = trim($clean);

        // Tiptap's empty document.
        return trim(strip_tags($clean, '<img>')) === '' ? null : $clean;
    }
}
