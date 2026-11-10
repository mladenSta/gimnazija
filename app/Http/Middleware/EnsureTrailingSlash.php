<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public URLs keep the WordPress structure with a trailing slash
 * (/dan-otvorenih-vrata-2/). "/slug" gets a 301 to "/slug/".
 * Paths with a file extension (sitemap.xml) are left alone.
 */
class EnsureTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if (in_array($request->method(), ['GET', 'HEAD'], true)
            && $path !== '/'
            && ! str_ends_with($path, '/')
            && ! str_contains(basename($path), '.')) {
            $query = $request->getQueryString();

            return redirect()->to($request->getSchemeAndHttpHost().$request->getBaseUrl().$path.'/'.($query ? "?{$query}" : ''), 301);
        }

        return $next($request);
    }
}
