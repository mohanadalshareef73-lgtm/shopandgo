<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SheinLinkClassifier
{
    public function __construct(private SheinUrlExtractor $urls)
    {
    }

    public function classify(string $url): string
    {
        if (strlen($url) > 8192 || ! $this->urls->isSheinUrl($url)) {
            return 'unknown';
        }
        $direct = $this->directType($url);
        if ($direct !== 'unknown') {
            return $direct;
        }
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');
        if ($host !== 'onelink.shein.com' && $host !== 'shein.top' && ! str_ends_with($host, '.shein.top')
            && ! str_contains($path, '/sharejump/')) {
            return 'unknown';
        }
        $key = 'shein-link-type-v1:'.hash('sha256', $url);
        $cached = Cache::get($key);
        if (in_array($cached, ['cart', 'product'], true)) {
            return $cached;
        }
        $type = $this->resolve($url);
        if ($type !== 'unknown') {
            Cache::put($key, $type, now()->addHours(6));
        }
        return $type;
    }

    private function directType(string $url): string
    {
        if (strtolower(parse_url($url, PHP_URL_HOST) ?? '') === 'onelink.shein.com') {
            return 'unknown';
        }
        $path = strtolower(rawurldecode(parse_url($url, PHP_URL_PATH) ?? ''));
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        if (preg_match('~-p-\d+\.html(?:/|$)|/(?:goods|product|goods-detail)(?:/|$)~', $path)
            || $this->hasValue($query, 'goods_id') || $this->hasValue($query, 'goodsId')) {
            return 'product';
        }
        $shareJump = in_array(rtrim($path, '/'), ['/h5/sharejump/appjump', '/h5/sharejump/appsharejump'], true);
        if (preg_match('~^/(?:[a-z]{2,3}(?:-[a-z]{2})?/)?cart(?:/|$)~', $path)
            || ($shareJump && $this->hasValue($query, 'cart'))
            || ($shareJump && ($query['cart_share'] ?? null) === '1' && $this->hasValue($query, 'group_id'))) {
            return 'cart';
        }
        return 'unknown';
    }

    private function hasValue(array $values, string $key): bool
    {
        return isset($values[$key]) && is_scalar($values[$key]) && (string) $values[$key] !== '';
    }

    private function resolve(string $url): string
    {
        try {
            $visited = [];
            for ($hop = 0; $hop < 4; $hop++) {
                // Validate each destination before requesting it, including redirects.
                if (! $this->urls->isSheinUrl($url) || strlen($url) > 8192 || isset($visited[$url])) {
                    return 'unknown';
                }
                $visited[$url] = true;
                $direct = $this->directType($url);
                if ($direct !== 'unknown') {
                    return $direct;
                }
                $response = Http::withOptions(['allow_redirects' => false, 'stream' => true])
                    ->connectTimeout(2)->timeout(5)->get($url);
                if ($response->redirect()) {
                    $url = $this->destination($url, $response->header('Location'));
                    continue;
                }
                if (! $response->successful()) {
                    return 'unknown';
                }
                $body = $response->toPsrResponse()->getBody();
                $html = '';
                while (! $body->eof() && strlen($html) < 262145) {
                    $chunk = $body->read(min(8192, 262145 - strlen($html)));
                    if ($chunk === '') {
                        break;
                    }
                    $html .= $chunk;
                }
                $body->close();
                if (strlen($html) > 262144) {
                    return 'unknown';
                }
                // The long share landing page includes one structured shareInfo object.
                // Its script library contains both cart and product paths; don't scan those.
                if (preg_match('/\b(?:var|let|const)\s+shareInfo\s*=\s*(\{[^\r\n]+\})\s*;/', $html, $match)) {
                    $info = json_decode($match[1], true);
                    if (is_array($info) && $this->hasValue($info, 'id')) {
                        if (($info['share_type'] ?? null) === 'cart' && in_array($info['cart_share'] ?? null, [1, '1'], true)) {
                            return 'cart';
                        }
                        if (in_array($info['share_type'] ?? null, ['goods', 'goods_detail'], true)) {
                            return 'product';
                        }
                    }
                }
                // The short onelink page identifies its destination in a hidden input.
                $document = new DOMDocument;
                @$document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new DOMXPath($document);
                $deepLink = $xpath->query('//input[@id="deeplink"]')->item(0)?->getAttribute('value');
                if (is_string($deepLink)) {
                    $type = $this->deepLinkType($deepLink);
                    if ($type !== 'unknown') {
                        return $type;
                    }
                }
                $fallback = $xpath->query('//input[@id="url"]')->item(0)?->getAttribute('value');
                if (! is_string($fallback) || $fallback === '') {
                    return 'unknown';
                }
                $url = $this->destination($url, $fallback);
            }
        } catch (\Throwable $e) {
            // An unresolved link must never become a paid cart submission.
        }
        return 'unknown';
    }

    private function destination(string $base, ?string $location): string
    {
        return $location ? (string) UriResolver::resolve(new Uri($base), new Uri($location)) : '';
    }

    private function deepLinkType(string $url): string
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'sheinlink' || parse_url($url, PHP_URL_HOST) !== 'applink') {
            return 'unknown';
        }
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        $data = is_string($query['data'] ?? null) ? json_decode($query['data'], true) : null;
        if (! is_array($data)) {
            return 'unknown';
        }
        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        if ($path === 'share_receiver' && in_array($data['cart_share'] ?? null, [1, '1'], true) && $this->hasValue($data, 'group_id')) {
            return 'cart';
        }
        return $path === 'goods' && $this->hasValue($data, 'goods_id') ? 'product' : 'unknown';
    }
}
