<?php

namespace App\Adapters\Http\Middleware;

use App\Adapters\Http\ErrorResponder;
use App\Adapters\Security\JwtTokenIssuer;
use Closure;
use Illuminate\Http\Request;
use Throwable;

/** CA-01.3: every endpoint except login and /health requires a token; wrong role → 403 (api-contract.md §4). */
final class AuthenticateJwt
{
    public function __construct(private JwtTokenIssuer $tokens)
    {
    }

    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return ErrorResponder::body(401, 'UNAUTHENTICATED', 'Se requiere autenticación.');
        }
        try {
            $claims = $this->tokens->decode(substr($header, 7));
        } catch (Throwable) {
            return ErrorResponder::body(401, 'UNAUTHENTICATED', 'Se requiere autenticación.');
        }
        if ($roles !== [] && ! in_array($claims['role'], $roles, true)) {
            return ErrorResponder::body(403, 'FORBIDDEN_ROLE', 'No tiene permiso para esta operación.');
        }
        $request->attributes->set('user_id', $claims['sub']);
        $request->attributes->set('role', $claims['role']);

        return $next($request);
    }
}
