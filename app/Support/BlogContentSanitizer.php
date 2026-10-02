<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class BlogContentSanitizer
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'h2', 'h3', 'h4', 'blockquote', 'ul', 'ol', 'li', 'a', 'span', 'pre', 'code', 'hr', 'div', 'figure', 'figcaption', 'img'];

    private const CLASSES = ['blog-lead', 'blog-callout', 'blog-image', 'blog-text-primary', 'blog-text-secondary', 'blog-text-accent', 'blog-text-success', 'blog-text-warning', 'blog-text-danger', 'blog-mark'];

    public function sanitize(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $body = $document->getElementsByTagName('body')->item(0);
            if (! $body) {
                return '';
            }
            $this->clean($body);

            return trim(implode('', array_map(fn (DOMNode $node) => $document->saveHTML($node), iterator_to_array($body->childNodes))));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function clean(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                if ($node->nodeType !== XML_TEXT_NODE) {
                    $parent->removeChild($node);
                }
                continue;
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math', 'template'], true)) {
                $parent->removeChild($node);
                continue;
            }
            $this->clean($node);
            if (! in_array($tag, self::TAGS, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = $attribute->value;
                $allowed = $name === 'title'
                    || ($name === 'dir' && in_array($value, ['ltr', 'rtl', 'auto'], true))
                    || ($tag === 'a' && $name === 'href' && $this->safeUrl($value, true))
                    || ($tag === 'a' && $name === 'target' && in_array($value, ['_blank', '_self'], true))
                    || ($tag === 'img' && $name === 'src' && $this->safeUrl($value))
                    || ($tag === 'img' && $name === 'alt')
                    || ($tag === 'img' && in_array($name, ['width', 'height'], true) && ctype_digit($value));
                if ($name === 'class') {
                    $classes = array_intersect(preg_split('/\s+/', $value) ?: [], self::CLASSES);
                    if ($classes !== []) {
                        $node->setAttribute('class', implode(' ', $classes));
                        continue;
                    }
                }
                if (! $allowed) {
                    $node->removeAttribute($attribute->name);
                }
            }
            if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private function safeUrl(string $value, bool $link = false): bool
    {
        $value = preg_replace('/[\x00-\x20\x7f]+/u', '', $value) ?? '';
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//') && ! str_contains($value, '\\')) {
            return true;
        }
        if ($link && str_starts_with($value, '#')) {
            return true;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), $link ? ['https', 'http', 'mailto', 'tel'] : ['https', 'http'], true);
    }
}
