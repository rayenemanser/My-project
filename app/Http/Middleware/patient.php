<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PatientMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isPatient()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Patient access required.'
            ], 403);
        }

        // تحميل بيانات المريض
        $request->user()->load('patient');

        return $next($request);
    }
}
