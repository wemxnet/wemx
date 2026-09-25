<?php

namespace Extensions\Modules\Marketplace\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

final class MarketplaceMarkdown
{
    /**
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'del', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'a', 'code', 'pre', 'blockquote', 'hr', 'img',
    ];

    public static function render(?string $markdown): string
    {
        $html = (string) Str::markdown($markdown ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return self::sanitize($html);
    }

    public static function sanitize(string $html): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return '';
        }

        self::clean($body);

        $safe = '';

        foreach ($body->childNodes as $child) {
            $safe .= $document->saveHTML($child);
        }

        return $safe;
    }

    private static function clean(DOMNode $node): void
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        $children = [];

        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        $unwrapped = false;

        foreach ($children as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                $unwrapped = true;

                continue;
            }

            self::keepSafeAttributes($child, $tag);
            self::clean($child);
        }

        if ($unwrapped) {
            self::clean($node);
        }
    }

    private static function keepSafeAttributes(DOMElement $element, string $tag): void
    {
        $href = $element->getAttribute('href');
        $src = $element->getAttribute('src');
        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $attributes[] = $attribute->name;
        }

        foreach ($attributes as $name) {
            $element->removeAttribute($name);
        }

        if ($tag === 'a') {
            $safeHref = self::safeUrl($href);

            if ($safeHref === null) {
                return;
            }

            $element->setAttribute('href', $safeHref);
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }

        if ($tag === 'img') {
            $safeSrc = self::safeUrl($src);

            if ($safeSrc === null || ! str_starts_with($safeSrc, 'http')) {
                $element->parentNode?->removeChild($element);

                return;
            }

            $element->setAttribute('src', $safeSrc);
            $element->setAttribute('alt', '');
        }
    }

    private static function safeUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($url === '' || preg_match('/[\x00-\x20]/', $url) === 1) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return null;
        }

        return $url;
    }
}
