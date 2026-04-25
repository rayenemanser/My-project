<?php
// app/Http/Middleware/PharmacistMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PharmacistMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isPharmacist()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Réservé aux pharmaciens.',
            ], 403);
        }

        $request->user()->load('pharmacistProfile');

        return $next($request);
    }
}
