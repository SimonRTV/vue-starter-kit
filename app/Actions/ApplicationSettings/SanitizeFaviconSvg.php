<?php

namespace App\Actions\ApplicationSettings;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

class SanitizeFaviconSvg
{
    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    /** @var list<string> */
    private const FORBIDDEN_ELEMENTS = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'set', 'handler', 'listener'];

    private const SAFE_REFERENCE_PATTERN = '/^(?:#|data:image\/(?:png|jpe?g|gif|webp);base64,)/i';

    /**
     * Strip scripting capabilities from an SVG so it is safe to serve from the application origin.
     *
     * @throws InvalidArgumentException When the contents are not a well-formed SVG document.
     */
    public function handle(string $svg): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;

        if (! $loaded || $document->doctype !== null || ! $root instanceof DOMElement
            || $root->localName !== 'svg' || $root->namespaceURI !== self::SVG_NAMESPACE) {
            throw new InvalidArgumentException('The favicon is not a valid SVG document.');
        }

        $elements = [];

        foreach ((new DOMXPath($document))->query('//*') ?: [] as $element) {
            if ($element instanceof DOMElement) {
                $elements[] = $element;
            }
        }

        foreach ($elements as $element) {
            if ($this->isForbidden($element)) {
                $element->parentNode?->removeChild($element);

                continue;
            }

            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->localName ?? $attribute->nodeName);

                if (str_starts_with($name, 'on')
                    || ($name === 'href' && preg_match(self::SAFE_REFERENCE_PATTERN, trim($attribute->nodeValue ?? '')) !== 1)) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }

        $sanitized = $document->saveXML($root);

        if (! is_string($sanitized)) {
            throw new InvalidArgumentException('The favicon is not a valid SVG document.');
        }

        return $sanitized;
    }

    private function isForbidden(DOMElement $element): bool
    {
        $name = strtolower($element->localName ?? $element->nodeName);

        if (in_array($name, self::FORBIDDEN_ELEMENTS, true)) {
            return true;
        }

        return str_starts_with($name, 'animate')
            && str_contains(strtolower($element->getAttribute('attributeName')), 'href');
    }
}
