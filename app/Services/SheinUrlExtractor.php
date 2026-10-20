<?php

namespace App\Services;

class SheinUrlExtractor
{
    public function extract(string $text): array
    {
        preg_match_all('~https?://[^\s<>"\x27]+~iu', $text, $matches);
        $urls = [];
        foreach ($matches[0] as $candidate) {
            $url = preg_replace('/[.,;!?\)\]\}،؛。]+$/u', '', $candidate);
            if ($this->isSheinUrl($url)) {
                $urls[] = $url;
            }
        }
        return array_values(array_unique($urls));
    }

    public function isSheinUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! $host || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return false;
        }
        foreach (config('services.cart_quotes.shein_hosts', ['shein.com']) as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed && ($host === $allowed || str_ends_with($host, '.'.$allowed))) {
                return true;
            }
        }
        return false;
    }
}
