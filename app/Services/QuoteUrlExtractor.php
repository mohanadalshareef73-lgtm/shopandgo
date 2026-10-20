<?php

namespace App\Services;

class QuoteUrlExtractor
{
    public const AMAZON_SUPPORTED_SITES = ['amazon.ae', 'amazon.sa', 'amazon.com'];

    private const AMAZON_SHARE_HOSTS = ['amzn.eu', 'amzn.to', 'a.co', 'amzn.asia'];

    // Recognize other Amazon marketplaces so the provider can reject them with
    // INVALID_URL instead of treating the customer's message as a general chat.
    private const AMAZON_OTHER_SITES = ['amazon.fr', 'amazon.co.uk', 'amazon.de', 'amazon.it',
        'amazon.es', 'amazon.ca', 'amazon.com.mx', 'amazon.com.br', 'amazon.co.jp', 'amazon.in',
        'amazon.com.au', 'amazon.sg', 'amazon.nl', 'amazon.se', 'amazon.pl', 'amazon.com.be',
        'amazon.ie', 'amazon.eg', 'amazon.com.tr', 'amazon.co.za', 'amazon.cn'];

    public function __construct(private SheinUrlExtractor $shein) {}

    public function isAmazonUrl(string $url): bool
    {
        $parts = parse_url($url);

        return in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['port'])
            && (in_array(preg_replace('/^www\./', '', strtolower($parts['host'] ?? '')),
                array_merge(self::AMAZON_SUPPORTED_SITES, self::AMAZON_OTHER_SITES), true)
                || in_array(strtolower($parts['host'] ?? ''), self::AMAZON_SHARE_HOSTS, true));
    }

    public function isUnsupportedAmazonSite(string $url): bool
    {
        return $this->isAmazonUrl($url)
            && in_array(preg_replace('/^www\./', '', strtolower(parse_url($url, PHP_URL_HOST) ?? '')), self::AMAZON_OTHER_SITES, true);
    }

    /** The provider validates iHerb paths and rejects plain HTTP links. */
    public function isIherbUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        return in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['port'])
            && ($host === 'iherb.co' || $host === 'iherb.com' || str_ends_with($host, '.iherb.com'));
    }

    /** Preserve source URLs: product/short-link validation belongs to the API. */
    public function supported(string $text): array
    {
        preg_match_all('~https?://[^\s<>"\x27]+~iu', $text, $matches);

        $urls = [];
        $hasIherb = false;
        foreach ($matches[0] as $candidate) {
            if ($this->isIherbUrl($candidate)) {
                if (! $hasIherb) {
                    // Send the first iHerb URL verbatim, including tracking parameters.
                    $urls[] = $candidate;
                    $hasIherb = true;
                }
                continue;
            }
            $url = preg_replace('/[.,;!?\)\]\}،؛。]+$/u', '', $candidate);
            if ($this->isAmazonUrl($url)
                || (strtolower(parse_url($url, PHP_URL_HOST) ?? '') === 'onelink.shein.com' && $this->shein->isSheinUrl($url))) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    public function extract(string $text): array
    {
        // Existing SHEIN-only messages retain their classifier and multi-cart behavior.
        $supported = $this->supported($text);
        if (array_filter($supported, fn ($url) => $this->isAmazonUrl($url))) {
            return $supported;
        }

        if (array_filter($supported, fn ($url) => $this->isIherbUrl($url))) {
            // Preserve all existing SHEIN links and their order in mixed messages.
            $shein = $this->shein->extract($text);
            preg_match_all('~https?://[^\s<>"\x27]+~iu', $text, $matches);
            return array_values(array_unique(array_filter(array_map(function ($candidate) use ($supported, $shein) {
                if ($this->isIherbUrl($candidate)) {
                    return in_array($candidate, $supported, true) ? $candidate : null;
                }
                $url = preg_replace('/[.,;!?\)\]\}،؛。]+$/u', '', $candidate);
                return in_array($url, $shein, true) ? $url : null;
            }, $matches[0]))));
        }

        return $this->shein->extract($text);
    }

}
