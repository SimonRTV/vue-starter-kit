<?php

namespace App\Actions\Pages;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class PageContent
{
    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks()
            ->allowElement('a', ['href', 'title'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->allowElement('ol', ['start'])
            ->withMaxInputLength(1000000);
        foreach (['p', 'br', 'h2', 'h3', 'strong', 'em', 'u', 's', 'ul', 'li', 'blockquote', 'pre', 'code', 'hr'] as $tag) {
            $config = $config->allowElement($tag);
        }

        $clean = (new HtmlSanitizer($config))->sanitize($html);

        return trim(strip_tags($clean)) === '' && ! str_contains($clean, '<hr') ? null : $clean;
    }

    public function render(?string $body, string $format = 'text'): string
    {
        if ($format === 'html') {
            return $this->sanitize($body) ?? '';
        }
        if ($body === null || trim($body) === '') {
            return '';
        }

        $paragraphs = preg_split('/\R{2,}/u', $body) ?: [];

        return implode('', array_map(fn (string $paragraph): string => '<p>'.nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</p>', $paragraphs));
    }
}
