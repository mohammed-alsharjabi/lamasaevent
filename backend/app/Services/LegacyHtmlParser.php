<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

class LegacyHtmlParser
{
    /**
     * @return array{
     *   title: string,
     *   summary: ?string,
     *   topic: ?string,
     *   published_at: ?string,
     *   content_blocks: array<int, array<string, mixed>>,
     *   images: array<int, array<string, mixed>>,
     *   service_links: array<int, string>,
     *   faqs: array<int, array{question: string, answer: string}>,
     *   menus: array<string, array<int, array{label: string, url: string}>>
     * }
     */
    public function parse(string $html, string $legacyFile): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException("Unable to parse {$legacyFile}");
        }

        $xpath = new DOMXPath($document);
        $main = $xpath->query('//main[@id="main"]')->item(0)
            ?? $xpath->query('//main')->item(0);

        if (! $main instanceof DOMElement) {
            throw new RuntimeException("No main element in {$legacyFile}");
        }

        $title = $this->firstText($xpath, './/h1', $main)
            ?? $this->firstText($xpath, '//title');
        if (! $title) {
            throw new RuntimeException("No page title in {$legacyFile}");
        }

        $summary = $this->meta($xpath, 'name', 'description')
            ?? $this->firstText($xpath, './/p', $main);
        $topic = $this->meta($xpath, 'property', 'article:section')
            ?? $this->firstTextByClasses(
                $xpath,
                $main,
                [
                    'bla-hero__tag',
                    'bla-hero__topic',
                    'blog-detail__topic',
                    'blog-card__topic',
                ],
            );

        $blocks = [];
        foreach ($main->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $blocks[] = $this->block($document, $xpath, $child);
        }

        $images = [];
        foreach ($xpath->query('.//img[@src]', $main) ?: [] as $index => $image) {
            if (! $image instanceof DOMElement) {
                continue;
            }

            $src = trim($image->getAttribute('src'));
            if (! str_starts_with($src, '/')) {
                continue;
            }

            $images[] = [
                'src' => $src,
                'alt' => $image->getAttribute('alt'),
                'width' => $this->nullableInteger($image->getAttribute('width')),
                'height' => $this->nullableInteger($image->getAttribute('height')),
                'loading' => $image->getAttribute('loading') ?: null,
                'role' => $index === 0 ? 'hero' : 'gallery',
            ];
        }

        $serviceLinks = [];
        foreach ($xpath->query('.//a[@href]', $main) ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = parse_url($link->getAttribute('href'), PHP_URL_PATH);
            if (
                is_string($href) &&
                preg_match('#^/services/([^/]+)/*$#', $href, $match) &&
                $match[1] !== 'category'
            ) {
                $serviceLinks[] = $match[1];
            }
        }

        $faqs = [];
        $faqNodes = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " blog-detail__faq-list ")]//li'
            .' | .//details[summary]',
            $main,
        );
        foreach ($faqNodes ?: [] as $faqNode) {
            $question = $this->firstText(
                $xpath,
                './/h3 | .//summary | .//*[contains(@class, "faq-q")]',
                $faqNode,
            );
            $answer = $this->firstText(
                $xpath,
                './/p | .//*[contains(@class, "faq-a")]',
                $faqNode,
            );

            if ($question && $answer) {
                $faqs[] = compact('question', 'answer');
            }
        }

        return [
            'title' => $title,
            'summary' => $summary,
            'topic' => $topic,
            'published_at' => $this->meta($xpath, 'property', 'article:published_time'),
            'content_blocks' => $blocks,
            'images' => array_values(array_unique($images, SORT_REGULAR)),
            'service_links' => array_values(array_unique($serviceLinks)),
            'faqs' => array_values(array_unique($faqs, SORT_REGULAR)),
            'menus' => [
                'header' => $this->navigationLinks($xpath, '//header//a[@href]'),
                'footer' => $this->navigationLinks($xpath, '//footer//a[@href]'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function block(
        DOMDocument $document,
        DOMXPath $xpath,
        DOMElement $element,
    ): array {
        $headings = [];
        foreach ($xpath->query('.//*[self::h1 or self::h2 or self::h3]', $element) ?: [] as $heading) {
            $headings[] = [
                'level' => strtolower($heading->nodeName),
                'id' => $heading instanceof DOMElement ? $heading->getAttribute('id') ?: null : null,
                'text' => $this->cleanText($heading->textContent),
            ];
        }

        $paragraphs = [];
        foreach ($xpath->query('.//p', $element) ?: [] as $paragraph) {
            $text = $this->cleanText($paragraph->textContent);
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        $links = [];
        foreach ($xpath->query('.//a[@href]', $element) ?: [] as $link) {
            if ($link instanceof DOMElement) {
                $links[] = [
                    'href' => $link->getAttribute('href'),
                    'text' => $this->cleanText($link->textContent),
                ];
            }
        }

        return [
            'type' => match (strtolower($element->tagName)) {
                'article' => 'article',
                'section' => 'section',
                'nav' => 'navigation',
                default => 'component',
            },
            'tag' => strtolower($element->tagName),
            'id' => $element->getAttribute('id') ?: null,
            'classes' => preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [],
            'headings' => $headings,
            'paragraphs' => $paragraphs,
            'links' => $links,
            'html' => $document->saveHTML($element),
        ];
    }

    private function firstText(
        DOMXPath $xpath,
        string $query,
        ?DOMNode $context = null,
    ): ?string {
        $node = $xpath->query($query, $context)->item(0);
        $text = $node ? $this->cleanText($node->textContent) : '';

        return $text !== '' ? $text : null;
    }

    /**
     * @param  array<int, string>  $classes
     */
    private function firstTextByClasses(
        DOMXPath $xpath,
        DOMNode $context,
        array $classes,
    ): ?string {
        foreach ($classes as $class) {
            $query = sprintf(
                './/*[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
                $class,
            );
            $value = $this->firstText($xpath, $query, $context);
            if ($value) {
                return $value;
            }
        }

        return null;
    }

    private function meta(DOMXPath $xpath, string $attribute, string $value): ?string
    {
        $node = $xpath->query(
            sprintf('//meta[translate(@%s, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%s"]', $attribute, strtolower($value)),
        )->item(0);

        if (! $node instanceof DOMElement) {
            return null;
        }

        return $node->getAttribute('content') ?: null;
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function nullableInteger(string $value): ?int
    {
        return ctype_digit($value) ? (int) $value : null;
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function navigationLinks(DOMXPath $xpath, string $query): array
    {
        $links = [];

        foreach ($xpath->query($query) ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $label = $this->cleanText($link->textContent);
            $url = trim($link->getAttribute('href'));

            if ($label !== '' && $url !== '' && ! str_starts_with($url, '#')) {
                $links[] = compact('label', 'url');
            }
        }

        return array_values(array_unique($links, SORT_REGULAR));
    }
}
