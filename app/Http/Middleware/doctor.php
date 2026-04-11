<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DoctorMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isDoctor()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Doctor access required.'
            ], 403);
        }

        // تحميل بيانات الطبيب
        $request->user()->load('doctor');

        return $next($request);
    }
}
