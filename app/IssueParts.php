<?php

namespace App;

use DOMDocument;
use DOMElement;
use DOMText;

/**
 * A piece of pictures and words in turn, as its parts in order: each run of
 * pictures one part, each run of words another, for a front page that sets
 * them side by side.
 */
class IssueParts
{
    /**
     * @return list<array{type: 'picture'|'words', html: string}>
     */
    public static function split(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $parts = [];

        foreach ($dom->documentElement?->childNodes ?? [] as $node) {
            if ($node instanceof DOMText && trim($node->textContent) === '') {
                continue;
            }

            $markup = (string) $dom->saveHTML($node);
            $picture = $node instanceof DOMElement && (in_array($node->nodeName, ['figure', 'img'], true) || (trim($node->textContent) === '' && str_contains($markup, '<img')));
            $type = $picture ? 'picture' : 'words';
            $last = array_key_last($parts);

            if ($last !== null && $parts[$last]['type'] === $type) {
                $parts[$last]['html'] .= $markup;
            } else {
                $parts[] = ['type' => $type, 'html' => $markup];
            }
        }

        return $parts;
    }
}
