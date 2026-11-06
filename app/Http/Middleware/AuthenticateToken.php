<?php

namespace App\Http\Middleware;

use App\Application\Ports\TokenIssuer;
use App\Http\Errors\ApiErrorRenderer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Requires `Authorization: Bearer <token>` (CA-01.3). Stores the claims in the request as "auth". */
final class AuthenticateToken
{
    public function __construct(private readonly TokenIssuer $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $claims = $token ? $this->tokens->verify($token) : null;
        if ($claims === null) {
            return ApiErrorRenderer::unauthenticated();
        }

        $request->attributes->set('auth', $claims);

        return $next($request);
    }
}
