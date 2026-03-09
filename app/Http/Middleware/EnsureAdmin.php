<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'errors' => null,
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
