<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'Patient Dashboard'
        ]);
    }
}
