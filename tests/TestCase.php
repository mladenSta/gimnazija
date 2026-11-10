<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel's test client strips trailing slashes; the public site uses
     * WordPress URLs with a trailing slash, so keep it when given.
     *
     * @param  string  $uri
     * @return string
     */
    protected function prepareUrlForRequest($uri)
    {
        $url = parent::prepareUrlForRequest($uri);
        $path = (string) parse_url($uri, PHP_URL_PATH);

        if ($path !== '/' && str_ends_with($path, '/') && ! str_ends_with((string) parse_url($url, PHP_URL_PATH), '/')) {
            $query = parse_url($url, PHP_URL_QUERY);
            $url = strtok($url, '?').'/'.($query ? "?{$query}" : '');
        }

        return $url;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
