<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $content = config('starter.features.public_site')
            ? "User-agent: *\nDisallow:\n\nSitemap: ".route('sitemap')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
