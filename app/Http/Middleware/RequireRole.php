<?php

namespace App\Http\Middleware;

use App\Application\Ports\TokenClaims;
use App\Http\Errors\ApiErrorRenderer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:admin') or 'role:admin,librarian'. Runs before the controller, so a 403
 * never reveals whether the resource exists (api-contract.md §4).
 */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $claims = $request->attributes->get('auth');
        if (! $claims instanceof TokenClaims) {
            return ApiErrorRenderer::unauthenticated();
        }
        if (! in_array($claims->role, $roles, true)) {
            return ApiErrorRenderer::forbidden();
        }

        return $next($request);
    }
}
