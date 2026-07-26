<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use HTMLPurifier;
use HTMLPurifier_Config;

class ArticleContentSanitizer
{
    private const DANGEROUS_TAGS = [
        'script', 'iframe', 'object', 'embed', 'applet', 'form', 'input',
        'button', 'meta', 'base', 'style', 'svg', 'math',
    ];

    private const URI_ATTRIBUTES = ['href', 'src', 'action', 'formaction'];

    public function sanitize(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,h1,h2,h3,h4,ul,ol,li,blockquote,a[href|title],img[src|alt|title|width|height],code,pre,hr,table,thead,tbody,tr,th,td');
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ]);
        $config->set('CSS.AllowedProperties', []);
        $config->set('Attr.EnableID', false);
        $config->set('AutoFormat.RemoveEmpty', false);

        return (new HTMLPurifier($config))->purify($html);
    }

    public function containsClearlyDangerousContent(string $html): bool
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($document->getElementsByTagName('*') as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            if (in_array(strtolower($element->tagName), self::DANGEROUS_TAGS, true)) {
                return true;
            }

            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim(html_entity_decode($attribute->value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if (str_starts_with($name, 'on')
                    || in_array($name, ['srcdoc', 'style'], true)
                    || ($this->isUriAttribute($name) && $this->hasUnsafeScheme($value))) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasMeaningfulText(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) !== '';
    }

    private function isUriAttribute(string $name): bool
    {
        return in_array($name, self::URI_ATTRIBUTES, true);
    }

    private function hasUnsafeScheme(string $value): bool
    {
        if (! preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $matches)) {
            return false;
        }

        return ! in_array(strtolower($matches[1]), ['http', 'https', 'mailto'], true);
    }
}
