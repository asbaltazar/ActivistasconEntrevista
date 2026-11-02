<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class CorsFilter implements FilterInterface
{
    /**
     * @param array<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $response = Services::response();
        $origin   = $request->getHeaderLine('Origin');
        $allowed  = array_map('trim', explode(',', (string) (env('RCT_CORS_ALLOWED_ORIGINS') ?? '*')));

        if ($origin !== '' && (in_array('*', $allowed, true) || in_array($origin, $allowed, true))) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
        } else {
            $response->setHeader('Access-Control-Allow-Origin', $allowed[0] ?? '*');
        }

        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Device-UUID');
        $response->setHeader('Access-Control-Allow-Credentials', 'true');

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $response->setStatusCode(204);
        }

        return null;
    }

    /**
     * @param array<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (!$response->hasHeader('Access-Control-Allow-Origin')) {
            $origin  = $request->getHeaderLine('Origin');
            $allowed = array_map('trim', explode(',', (string) (env('RCT_CORS_ALLOWED_ORIGINS') ?? '*')));
            $response->setHeader('Access-Control-Allow-Origin', ($origin !== '' && in_array($origin, $allowed, true)) ? $origin : ($allowed[0] ?? '*'));
            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Device-UUID');
        }
        return $response;
    }
}
