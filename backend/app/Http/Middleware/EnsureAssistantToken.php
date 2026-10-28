<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When ASSISTANT_TOKEN is set, every call must carry it. When it is empty the
 * assistant is open to anyone who can reach the API (the owner's choice).
 */
class EnsureAssistantToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('assistant.token');

        if ($expected === '') {
            return $next($request);
        }

        if (! hash_equals($expected, (string) $request->header('X-Assistant-Token'))) {
            return response()->json(['message' => 'Wrong or missing assistant access code.'], 401);
        }

        return $next($request);
    }
}
