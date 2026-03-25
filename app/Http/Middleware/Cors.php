<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflight($request);
        }

        $response = $next($request);
        $this->addCorsHeaders($response, $request);

        return $response;
    }

    private function handlePreflight(Request $request): Response
    {
        $response = response('', 204);
        $this->addCorsHeaders($response, $request);

        return $response;
    }

    private function addCorsHeaders(Response $response, Request $request): void
    {
        $origin = $request->header('Origin');
        $allowed = $origin && (
            str_starts_with($origin, 'http://localhost') ||
            str_starts_with($origin, 'http://127.0.0.1')
        );

        $response->headers->set('Access-Control-Allow-Origin', $allowed ? $origin : '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
    }
}
