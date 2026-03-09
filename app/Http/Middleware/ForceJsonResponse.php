<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        // forza sempre JSON per /api/*
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
