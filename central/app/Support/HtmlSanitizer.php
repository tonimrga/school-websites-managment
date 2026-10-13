<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizes rich text (articles.body, pages.body) on save with the allow-list
 * from SPEC section 8.
 *
 * Allowed tags keep only the attributes listed below. Any other tag is
 * unwrapped (its text is kept), except for the dropped elements, which are
 * removed together with their content.
 */
class HtmlSanitizer
{
    /**
     * @var array<string, list<string>>
     */
    public const ALLOWED_ELEMENTS = [
        'p' => [], 'br' => [],
        'h2' => [], 'h3' => [], 'h4' => [],
        'strong' => [], 'em' => [], 'u' => [], 's' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href'],
        'blockquote' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
    ];

    public const ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Elements removed together with everything inside them.
     */
    protected const DROPPED_ELEMENTS = [
        'script', 'style', 'template', 'noscript', 'iframe', 'frame', 'frameset',
        'object', 'embed', 'applet', 'svg', 'math', 'img', 'picture', 'video',
        'audio', 'source', 'track', 'canvas', 'form', 'input', 'button', 'select',
        'textarea', 'option', 'head', 'title', 'meta', 'link', 'base',
    ];

    /**
     * Well above any real article; Symfony's default (20 000 characters)
     * would silently truncate long bodies.
     */
    protected const MAX_INPUT_LENGTH = 2_000_000;

    protected ?SymfonyHtmlSanitizer $sanitizer = null;

    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return $this->sanitizer()->sanitize($this->removeHeadOnlyElements($html));
    }

    /**
     * Symfony sanitizes in a body context, where configuration for head-only
     * elements is ignored; with the "block" default the contents of <style>
     * and <title> would leak into the text. These raw-text elements end at
     * their first closing tag, so a pattern removes them reliably.
     */
    protected function removeHeadOnlyElements(string $html): string
    {
        return (string) preg_replace('#<(style|title)\b[^>]*>.*?</\1\s*>#is', '', $html);
    }

    protected function sanitizer(): SymfonyHtmlSanitizer
    {
        return $this->sanitizer ??= new SymfonyHtmlSanitizer($this->config());
    }

    protected function config(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig)
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowLinkSchemes(self::ALLOWED_LINK_SCHEMES)
            ->allowRelativeLinks(false)
            ->withMaxInputLength(self::MAX_INPUT_LENGTH);

        foreach (self::ALLOWED_ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        foreach (self::DROPPED_ELEMENTS as $element) {
            $config = $config->dropElement($element);
        }

        return $config;
    }
}
