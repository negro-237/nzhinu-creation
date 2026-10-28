<?php

namespace App\Http\Controllers;

use App\Services\SearchIndex;

class SearchIndexController extends Controller
{
    public function sitemap(SearchIndex $index)
    {
        return response()->stream(function () use ($index) {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($index->urls() as $url) {
                echo '<url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'."\n";
            }
            echo '</urlset>';
        }, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'X-Robots-Tag' => 'noindex']);
    }

    public function robots(SearchIndex $index)
    {
        $text = "User-agent: *\n";
        if (! config('seo.indexable')) {
            $text .= "Disallow: /\n";
        } else {
            $text .= "Disallow: /admin\nDisallow: /livewire\nDisallow: /panier\nDisallow: /commander\nDisallow: /commandes/\n";
            $text .= 'Sitemap: '.$index->url('/sitemap.xml')."\n";
        }

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
