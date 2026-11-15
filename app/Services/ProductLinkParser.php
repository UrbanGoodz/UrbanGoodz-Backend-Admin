<?php

namespace App\Services;

/**
 * Pulls what a shopper cares about out of a product page: name, picture, price. Structured data first
 * (schema.org JSON-LD, then OpenGraph/product meta tags), the page title as a last resort. Nothing here is
 * trusted as a final price: it is a starting point the customer and the driver confirm.
 */
class ProductLinkParser
{
    /** @return array{title: ?string, price: ?float, currency: ?string, image: ?string, site_name: ?string, source: string} */
    public function parse(string $html, string $pageUrl): array
    {
        $out = ['title' => null, 'price' => null, 'currency' => null, 'image' => null, 'site_name' => null, 'source' => 'none'];
        if (trim($html) === '') {
            return $out;
        }

        $dom = new \DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $meta = [];
        foreach ($dom->getElementsByTagName('meta') as $m) {
            $key = strtolower($m->getAttribute('property') ?: $m->getAttribute('name') ?: $m->getAttribute('itemprop'));
            if ($key !== '' && ! isset($meta[$key])) {
                $meta[$key] = trim($m->getAttribute('content'));
            }
        }

        // 1) schema.org Product in JSON-LD
        foreach ($dom->getElementsByTagName('script') as $s) {
            if (strtolower($s->getAttribute('type')) !== 'application/ld+json') {
                continue;
            }
            $data = json_decode(trim($s->textContent), true);
            if ($data === null) {
                continue;
            }
            $product = $this->findProduct($data);
            if ($product) {
                $offer = $this->firstOffer($product['offers'] ?? null);
                $out['title'] = $this->clean($product['name'] ?? null);
                $out['price'] = $this->price($offer['price'] ?? ($offer['lowPrice'] ?? ($offer['priceSpecification']['price'] ?? null)));
                $out['currency'] = $this->currency($offer['priceCurrency'] ?? ($offer['priceSpecification']['priceCurrency'] ?? null));
                $out['image'] = $this->image($product['image'] ?? null, $pageUrl);
                $out['source'] = 'json_ld';
                break;
            }
        }

        // 2) OpenGraph / product meta, filling whatever is still missing
        $out['title'] = $out['title'] ?: $this->clean($meta['og:title'] ?? ($meta['twitter:title'] ?? null));
        $out['price'] = $out['price'] ?? $this->price($meta['product:price:amount'] ?? ($meta['og:price:amount'] ?? ($meta['price'] ?? null)));
        $out['currency'] = $out['currency'] ?: $this->currency($meta['product:price:currency'] ?? ($meta['og:price:currency'] ?? ($meta['pricecurrency'] ?? null)));
        $out['image'] = $out['image'] ?: $this->image($meta['og:image'] ?? ($meta['twitter:image'] ?? null), $pageUrl);
        $out['site_name'] = $this->clean($meta['og:site_name'] ?? null);
        if ($out['source'] === 'none' && ($out['title'] || $out['price'] !== null)) {
            $out['source'] = 'opengraph';
        }

        // 3) the page title
        if (! $out['title']) {
            $t = $dom->getElementsByTagName('title')->item(0);
            $out['title'] = $t ? $this->clean($t->textContent) : null;
            if ($out['title'] && $out['source'] === 'none') {
                $out['source'] = 'title';
            }
        }

        return $out;
    }

    private function findProduct($node): ?array
    {
        if (! is_array($node)) {
            return null;
        }
        $type = $node['@type'] ?? null;
        if ($type !== null && in_array('Product', (array) $type, true)) {
            return $node;
        }
        foreach ($node as $child) {
            if (is_array($child) && ($found = $this->findProduct($child))) {
                return $found;
            }
        }

        return null;
    }

    private function firstOffer($offers): array
    {
        if (! is_array($offers)) {
            return [];
        }

        return array_is_list($offers) ? (is_array($offers[0] ?? null) ? $offers[0] : []) : $offers;
    }

    private function price($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = preg_replace('/[^0-9.,]/', '', (string) $v);
        if ($n === '' || $n === null) {
            return null;
        }
        // "1,299.99" and "1299,99" both occur; the last separator is the decimal point when it has 1-2 digits after.
        if (preg_match('/^(.*)[.,](\d{1,2})$/', $n, $m)) {
            $n = str_replace([',', '.'], '', $m[1]).'.'.$m[2];
        } else {
            $n = str_replace([',', '.'], '', $n);
        }
        $f = (float) $n;

        return ($f > 0 && $f < 100000) ? round($f, 2) : null;
    }

    private function currency($v): ?string
    {
        $c = strtoupper(trim((string) $v));

        return preg_match('/^[A-Z]{3}$/', $c) ? $c : null;
    }

    private function clean($v): ?string
    {
        if ($v === null) {
            return null;
        }
        $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $v), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $t === '' ? null : mb_substr($t, 0, 200);
    }

    private function image($v, string $pageUrl): ?string
    {
        if (is_array($v)) {
            $v = is_string($v[0] ?? null) ? $v[0] : ($v['url'] ?? ($v[0]['url'] ?? null));
        }
        $v = is_string($v) ? trim($v) : '';
        if ($v === '') {
            return null;
        }
        if (str_starts_with($v, '//')) {
            $v = 'https:'.$v;
        } elseif (str_starts_with($v, '/')) {
            $p = parse_url($pageUrl);
            $v = ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').$v;
        }

        return preg_match('#^https?://#i', $v) ? mb_substr($v, 0, 1000) : null;
    }
}
