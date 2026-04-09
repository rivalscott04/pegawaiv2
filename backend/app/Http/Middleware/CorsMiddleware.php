<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is a public endpoint - allow all origins
        $isPublicEndpoint = $request->is('api/public/*');
        
        // Get allowed origins from environment
        $allowedOrigins = explode(',', env('ALLOWED_ORIGINS', 'https://sdm.rivaldev.site,http://localhost:3800'));
        $allowedOrigins = array_map('trim', $allowedOrigins);
        
        $origin = $request->headers->get('Origin');

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 204);
            $this->addCorsHeaders($response, $origin, $allowedOrigins, $request, $isPublicEndpoint);
            return $response;
        }

        // Process request normally - let Laravel handle exceptions
        /** @var Response $response */
        $response = $next($request);

        // Tambahkan CORS headers ke response
        $this->addCorsHeaders($response, $origin, $allowedOrigins, $request, $isPublicEndpoint);

        return $response;
    }

    /**
     * Add CORS headers to response
     */
    private function addCorsHeaders(Response $response, ?string $origin, array $allowedOrigins, Request $request, bool $isPublicEndpoint = false): void
    {
        // For public endpoints, allow all origins (disable CORS restriction)
        if ($isPublicEndpoint) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
            $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours
            return;
        }

        // For protected endpoints, use strict CORS policy
        // Check if origin is allowed
        if ($origin && in_array($origin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        } else {
            // For same-origin requests (no Origin header) or null origin, allow it if same origin
            if (!$origin || $origin === 'null') {
                // Same-origin request - allow it
                $response->headers->set('Access-Control-Allow-Origin', $request->getSchemeAndHttpHost());
            } else {
                // Jika origin tidak diizinkan, tetap set header untuk menghindari CORS error
                // Browser akan tetap memblokir, tapi setidaknya response tidak kosong
                $response->headers->set('Access-Control-Allow-Origin', $allowedOrigins[0] ?? '*');
            }
        }

        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
        $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours
    }
}


