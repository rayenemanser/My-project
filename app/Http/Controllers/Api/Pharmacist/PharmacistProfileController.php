<?php
// app/Http/Controllers/Api/Pharmacist/PharmacistProfileController.php

namespace App\Http\Controllers\Api\Pharmacist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PharmacistProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('pharmacistProfile');

        return response()->json([
            'success' => true,
            'data'    => [
                'user'    => $user->only(['id','name','email','phone','avatar','city','status','is_online','last_login_at']),
                'profile' => $user->pharmacistProfile,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'                  => 'sometimes|string|max:255',
            'phone'                 => 'sometimes|string|max:20',
            'city'                  => 'sometimes|string|max:100',
            'wilaya'                => 'sometimes|string|max:100',
            'pharmacy_name'         => 'sometimes|string|max:255',
            'address'               => 'sometimes|string|max:500',
            'qualifications'        => 'sometimes|string',
            'experience_years'      => 'sometimes|integer|min:0',
            'certifications'        => 'sometimes|string',
            'insurance_accepted'    => 'sometimes|string',
            'specialized_equipment' => 'sometimes|string',
            'additional_notes'      => 'sometimes|string',
            'is_available'          => 'sometimes|boolean',
        ]);

        $user->update(array_intersect_key($validated, array_flip(['name','phone','city'])));

        $user->pharmacistProfile()->update(
            array_diff_key($validated, array_flip(['name','phone','city']))
        );

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'data'    => $user->load('pharmacistProfile'),
        ]);
    }
}
