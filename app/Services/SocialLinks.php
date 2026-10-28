<?php

namespace App\Services;

class SocialLinks
{
    public function publicLinks(): array
    {
        $links = [];
        foreach (['facebook' => ['Facebook', ['facebook.com', 'www.facebook.com', 'm.facebook.com']],
            'tiktok' => ['TikTok', ['tiktok.com', 'www.tiktok.com']],
            'youtube' => ['YouTube', ['youtube.com', 'www.youtube.com', 'youtu.be']]] as $network => [$label, $hosts]) {
            $url = trim((string) config('communications.social_'.$network));
            $parts = parse_url($url);
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
                || ! in_array(strtolower($parts['host'] ?? ''), $hosts, true) || isset($parts['user']) || isset($parts['pass'])
                || (isset($parts['port']) && $parts['port'] !== 443)) {
                continue;
            }
            $links[] = compact('network', 'label', 'url');
        }

        return $links;
    }
}
