<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientProfileController extends Controller
{
    /**
     * GET /api/patient/profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('patientProfile');

        return response()->json(['data' => [
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'avatar'       => $user->avatar_url,
            'initials'     => $user->initials,
            'gender'       => $user->gender,
            'date_of_birth'=> $user->date_of_birth?->toDateString(),
            'age'          => $user->age,
            'address'      => $user->address,
            'city'         => $user->city,
            'profile'      => $user->patientProfile,
        ]]);
    }

    /**
     * PUT /api/patient/profile
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name'                     => 'sometimes|string|max:255',
            'phone'                    => 'nullable|string|max:20',
            'gender'                   => 'nullable|in:male,female',
            'date_of_birth'            => 'nullable|date|before:today',
            'address'                  => 'nullable|string|max:500',
            'city'                     => 'nullable|string|max:100',
            // profile fields
            'blood_type'               => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'height'                   => 'nullable|numeric|min:50|max:250',
            'weight'                   => 'nullable|numeric|min:10|max:300',
            'allergies'                => 'nullable|array',
            'chronic_conditions'       => 'nullable|array',
            'current_medications'      => 'nullable|array',
            'emergency_contact_name'   => 'nullable|string|max:255',
            'emergency_contact_phone'  => 'nullable|string|max:20',
            'emergency_contact_relation'=> 'nullable|string|max:100',
            'insurance_provider'       => 'nullable|string|max:255',
            'insurance_number'         => 'nullable|string|max:100',
            'wilaya'                   => 'nullable|string|max:100',
            'occupation'               => 'nullable|string|max:100',
            'marital_status'           => 'nullable|in:single,married,divorced,widowed',
        ]);

        $user = $request->user();

        $user->update($request->only(['name', 'phone', 'gender', 'date_of_birth', 'address', 'city']));

        $profileFields = $request->only([
            'blood_type', 'height', 'weight', 'allergies',
            'chronic_conditions', 'current_medications',
            'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
            'insurance_provider', 'insurance_number', 'wilaya',
            'occupation', 'marital_status',
        ]);

        if (!empty($profileFields)) {
            $user->patientProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileFields
            );
        }

        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'image|mimes:jpg,jpeg,png,webp|max:2048']);
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar' => $path]);
        }

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => $user->fresh('patientProfile'),
        ]);
    }
}
