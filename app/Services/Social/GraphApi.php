<?php

namespace App\Services\Social;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Meta Graph API calls (Facebook pages and Instagram business accounts).
 */
class GraphApi
{
    public const BASE = 'https://graph.facebook.com';

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws SocialPublishException
     */
    public function post(string $path, array $params, string $token): array
    {
        return $this->send('post', $path, $params, $token);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws SocialPublishException
     */
    public function get(string $path, array $params, string $token): array
    {
        return $this->send('get', $path, $params, $token);
    }

    /**
     * @param  'get'|'post'  $method
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws SocialPublishException
     */
    private function send(string $method, string $path, array $params, string $token): array
    {
        $url = self::BASE.'/'.config('cms.social_publishing.graph_version').'/'.ltrim($path, '/');
        $params['access_token'] = $token;

        try {
            $request = Http::timeout((int) config('cms.social_publishing.timeout'))->acceptJson();
            $response = $method === 'post' ? $request->asForm()->post($url, $params) : $request->get($url, $params);
        } catch (ConnectionException) {
            throw new SocialPublishException('Nije moguće povezati se sa Meta (Facebook) serverom. Pokušajte ponovo.');
        }

        if ($response->failed()) {
            throw new SocialPublishException(self::error($response));
        }

        return (array) $response->json();
    }

    private static function error(Response $response): string
    {
        $error = (array) $response->json('error', []);
        $code = (int) ($error['code'] ?? 0);
        $text = trim((string) ($error['error_user_msg'] ?? $error['message'] ?? ''));

        $hint = match (true) {
            $code === 190 => 'Token je istekao ili nije ispravan — unesite novi u Podešavanjima.',
            in_array($code, [10, 200, 210], true) || ($code >= 200 && $code < 300) => 'Token nema potrebne dozvole za objavljivanje.',
            $code === 100 => 'Neispravan zahtjev (provjerite ID naloga u Podešavanjima).',
            $code === 4, $code === 17, $code === 32, $code === 613 => 'Previše zahtjeva — pokušajte kasnije.',
            default => null,
        };

        $parts = array_filter([$hint, $text !== '' ? "Odgovor: {$text}" : "HTTP {$response->status()}"]);

        return implode(' ', $parts);
    }
}
