<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtractBearerTokenFromQuery
{
    /**
     * Handle an incoming request.
     * Mengizinkan download berkas/PDF via direct link browser/mobile
     * dengan menyalin ?token=... atau ?api_token=... ke header Authorization Bearer jika belum ada.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->has('Authorization')) {
            $token = $request->query('token') ?: $request->query('api_token');
            if ($token && is_string($token)) {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}
