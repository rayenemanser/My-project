<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient as PatientProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\ForgotPasswordMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{
    // Constants
    const ROLES = [
        'doctor' => 'DOCTOR',
        'pharmacist' => 'PHARMACIEN',
        'patient' => 'PATIENT',
        'entrepreneur' => 'ENTREPRENEUR_PHARMACEUTIQUE'
    ];

    const REDIRECT_PATHS = [
        'DOCTOR' => '/Doctor.dashboard.html',
        'PHARMACIEN' => '/Pharmacie dashboard.html',
        'PATIENT' => '/Patient.dashboard.html',
        'ENTREPRENEUR_PHARMACEUTIQUE' => '/entrepreneur.html'
    ];

    const ACCOUNT_STATUS = [
        'active' => 'active',
        'pending' => 'pending',
        'suspended' => 'suspended',
        'inactive' => 'inactive',
        'blocked' => 'blocked'
    ];

    /**
     * Constructor with middleware
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only([
            'logout',
            'checkAuth',
            'getUserProfile',
            'updateProfile',
            'refreshToken',
            'resendVerificationEmail',
            'deactivateAccount'
        ]);
    }

    /**
     * User Registration
     */
    public function register(Request $request)
    {
        // 1. Common Validation (Base User Fields)
        $rules = [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:' . implode(',', array_values(self::ROLES)),
            'phone' => 'nullable|string|max:20',
        ];

        // 2. Role-Specific Validation Rules
        switch ($request->role) {
            case self::ROLES['doctor']:
                $rules = array_merge($rules, [
                    'full_name' => 'required|string|max:255',
                    'specialty' => 'required|string|max:255',
                    'license_number' => 'required|string|max:100',
                    'years_experience' => 'required|integer|min:0',
                    'hospital_affiliation' => 'nullable|string|max:255',
                    'education' => 'nullable|string',
                ]);
                break;

            case self::ROLES['patient']:
                $rules = array_merge($rules, [
                    'full_name' => 'required|string|max:255',
                    'date_of_birth' => 'required|date',
                    'gender' => 'required|in:Male,Female,Other,Prefer not to say',
                    'address' => 'nullable|string|max:500',
                ]);
                break;

            case self::ROLES['pharmacist']:
                $rules = array_merge($rules, [
                    'pharmacist_name' => 'required|string|max:255',
                    'pharmacy_name' => 'required|string|max:255',
                    'license_number' => 'required|string|max:100', // Pharmacy License
                    'pharmacist_license' => 'required|string|max:100', // Personal License
                    'address' => 'nullable|string|max:500',
                ]);
                break;

            case self::ROLES['entrepreneur']:
                $rules = array_merge($rules, [
                    'full_name' => 'required|string|max:255',
                    'company_name' => 'required|string|max:255',
                    'business_registration' => 'required|string|max:100',
                ]);
                break;
        }

        // 3. Validate Request
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // 4. Prepare User Data & Map Fields
        $userData = [
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'phone' => $request->phone,
            'status' => self::ACCOUNT_STATUS['active'],
            'name' => $request->full_name ?? $request->pharmacist_name,
        ];

        // 5. Create User & Return Response
        try {
            DB::beginTransaction();
            $user = User::create($userData);

            // Create specific profile based on role
            if ($request->role === self::ROLES['doctor']) {
                Doctor::create([
                    'user_id' => $user->id,
                    'specialty' => $request->specialty,
                    'license_number' => $request->license_number,
                    'experience_years' => $request->years_experience,
                    'hospital' => $request->hospital_affiliation,
                    'education' => $request->education,
                ]);
            } elseif ($request->role === self::ROLES['patient']) {
                PatientProfile::create([
                    'user_id' => $user->id,
                    'doctor_id' => $request->doctor_id, // Patients should select a doctor or assign later
                    'name' => $user->name,
                    'email' => $user->email,
                    'date_of_birth' => $request->date_of_birth,
                    'blood_type' => $request->blood_group ?? 'Unknown',
                ]);
            }

            DB::commit();

            // Create Token
            $token = $user->createToken(
                'MedicalAppToken',
                $this->getTokenAbilities($user->role),
                now()->addHours(12)
            )->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $this->prepareUserData($user),
                    'auth' => [
                        'access_token' => $token,
                        'token_type' => 'Bearer',
                        'expires_in' => 43200,
                    ],
                    'redirect_to' => self::REDIRECT_PATHS[$user->role] ?? '/dashboard',
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * User Login
     */
    public function login(Request $request)
    {
        // Rate limiting
        $key = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'status' => 'error',
                'message' => 'محاولات تسجيل دخول كثيرة. حاول مرة أخرى بعد ' . ceil($seconds / 60) . ' دقيقة.'
            ], 429);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'role' => 'required|in:' . implode(',', array_values(self::ROLES)),
            'remember_me' => 'sometimes|boolean'
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون على الأقل 8 أحرف',
            'role.required' => 'الرجاء اختيار الدور',
            'role.in' => 'الدور غير صالح'
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($key, 300);
            return response()->json([
                'status' => 'error',
                'message' => 'فشل التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find user
        $user = User::where('email', $request->email)->first();

        // Check credentials
        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 300);
            return response()->json([
                'status' => 'error',
                'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'
            ], 401);
        }

        // Check role
        if ($user->role !== $request->role) {
            RateLimiter::hit($key, 300);
            return response()->json([
                'status' => 'error',
                'message' => 'لا تملك صلاحية الدخول بهذا الدور'
            ], 403);
        }

        // Check account status
        if ($user->status !== self::ACCOUNT_STATUS['active']) {
            $messages = [
                'pending' => 'الحساب قيد المراجعة. يرجى الانتظار حتى يتم تفعيله.',
                'suspended' => 'الحساب موقوف. يرجى التواصل مع الإدارة.',
                'inactive' => 'الحساب غير نشط. يرجى تفعيله.',
                'blocked' => 'الحساب محظور لأسباب أمنية.'
            ];

            return response()->json([
                'status' => 'error',
                'message' => $messages[$user->status] ?? 'الحساب غير مفعل'
            ], 403);
        }

        // Check email verification
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'error',
                'message' => 'يرجى تفعيل بريدك الإلكتروني أولاً'
            ], 403);
        }

        // Logout from other devices if requested
        if ($request->boolean('single_session')) {
            $user->tokens()->delete();
        }

        // Create token with abilities
        $token = $user->createToken(
            'MedicalAppToken',
            $this->getTokenAbilities($user->role),
            now()->addHours(12)
        )->plainTextToken;

        // Update user login info
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'login_count' => $user->login_count + 1,
            'is_online' => true
        ]);

        // Log activity
        $this->logActivity($user, 'login', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Check for unusual login
        if ($this->isUnusualLogin($user, $request)) {
            $this->sendSecurityAlert($user, $request, 'unusual_login');
        }

        // Prepare response
        $response = [
            'status' => 'success',
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'user' => $this->prepareUserData($user),
                'auth' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => 43200, // 12 hours in seconds
                    'expires_at' => now()->addHours(12)->toIso8601String()
                ],
                'redirect_to' => self::REDIRECT_PATHS[$user->role] ?? '/dashboard',
                'permissions' => $this->getUserPermissions($user->role)
            ]
        ];

        // Add remember token if requested
        if ($request->boolean('remember_me')) {
            $rememberToken = $this->createRememberToken($user);
            $response['data']['auth']['remember_token'] = $rememberToken;
        }

        // Clear rate limiter
        RateLimiter::clear($key);

        return response()->json($response);
    }

    /**
     * User Logout
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        // Log activity
        $this->logActivity($user, 'logout');

        // Update user status
        $user->update(['is_online' => false]);

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Clear session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request)
    {
        $user = $request->user();

        // Log activity
        $this->logActivity($user, 'logout_all');

        // Update user status
        $user->update(['is_online' => false]);

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الخروج من جميع الأجهزة بنجاح'
        ]);
    }

    /**
     * Forgot Password
     */
    public function forgotPassword(Request $request)
    {
        // Rate limiting
        $key = 'forgot-password:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'status' => 'error',
                'message' => 'محاولات كثيرة. حاول مرة أخرى بعد 15 دقيقة'
            ], 429);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
            'email.exists' => 'البريد الإلكتروني غير مسجل'
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($key, 900);
            return response()->json([
                'status' => 'error',
                'message' => 'فشل التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find user
        $user = User::where('email', $request->email)->first();

        // Check if user is active
        if ($user->status !== self::ACCOUNT_STATUS['active']) {
            return response()->json([
                'status' => 'error',
                'message' => 'لا يمكن طلب إعادة تعيين كلمة المرور للحسابات غير النشطة'
            ], 403);
        }

        // Generate reset token
        $resetToken = Str::random(64);
        $hashedToken = hash('sha256', $resetToken);

        $user->update([
            'reset_password_token' => $hashedToken,
            'reset_password_expires' => now()->addHours(2)
        ]);

        // Send email
        try {
            Mail::to($user->email)->send(new ForgotPasswordMail($user, $resetToken));

            // Log activity
            $this->logActivity($user, 'password_reset_requested');

            RateLimiter::clear($key);

            return response()->json([
                'status' => 'success',
                'message' => 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني',
                'expires_in' => 7200 // 2 hours in seconds
            ]);
        } catch (\Exception $e) {
            Log::error('Password reset email failed: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'فشل إرسال البريد الإلكتروني. يرجى المحاولة لاحقاً.'
            ], 500);
        }
    }

    /**
     * Reset Password
     */
    public function resetPassword(Request $request)
    {
        // Rate limiting
        $key = 'reset-password:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'status' => 'error',
                'message' => 'محاولات كثيرة. حاول مرة أخرى بعد 15 دقيقة'
            ], 429);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:64',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed|different:old_password',
            'password_confirmation' => 'required|string'
        ], [
            'token.required' => 'الرمز المميز مطلوب',
            'token.size' => 'الرمز المميز غير صالح',
            'password.min' => 'كلمة المرور يجب أن تكون على الأقل 8 أحرف',
            'password.confirmed' => 'كلمات المرور غير متطابقة',
            'password.different' => 'كلمة المرور الجديدة يجب أن تختلف عن القديمة'
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($key, 900);
            return response()->json([
                'status' => 'error',
                'message' => 'فشل التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find user
        $user = User::where('email', $request->email)->first();

        // Validate token
        if (!$user ||
            !$user->reset_password_token ||
            !hash_equals($user->reset_password_token, hash('sha256', $request->token)) ||
            !$user->reset_password_expires ||
            $user->reset_password_expires->isPast()) {

            RateLimiter::hit($key, 900);
            return response()->json([
                'status' => 'error',
                'message' => 'رابط إعادة تعيين كلمة المرور غير صالح أو منتهي الصلاحية'
            ], 400);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
            'reset_password_token' => null,
            'reset_password_expires' => null,
            'password_changed_at' => now(),
            'last_password_change' => now()
        ]);

        // Revoke all tokens (force logout from all devices)
        $user->tokens()->delete();

        // Log activity
        $this->logActivity($user, 'password_reset_success');

        // Send security alert
        $this->sendSecurityAlert($user, $request, 'password_changed');

        RateLimiter::clear($key);

        return response()->json([
            'status' => 'success',
            'message' => 'تم إعادة تعيين كلمة المرور بنجاح'
        ]);
    }

    /**
     * Change Password (when authenticated)
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        // Validation
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed|different:current_password',
            'new_password_confirmation' => 'required|string'
        ], [
            'current_password.required' => 'كلمة المرور الحالية مطلوبة',
            'new_password.min' => 'كلمة المرور الجديدة يجب أن تكون على الأقل 8 أحرف',
            'new_password.confirmed' => 'كلمات المرور غير متطابقة',
            'new_password.different' => 'كلمة المرور الجديدة يجب أن تختلف عن الحالية'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'فشل التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'كلمة المرور الحالية غير صحيحة'
            ], 400);
        }

        // Check password history (prevent reuse)
        if ($this->isPasswordInHistory($user, $request->new_password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'لا يمكن استخدام كلمة مرور سبق استخدامها'
            ], 400);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
            'password_changed_at' => now(),
            'last_password_change' => now()
        ]);

        // Add to password history
        $this->addToPasswordHistory($user, $request->new_password);

        // Log activity
        $this->logActivity($user, 'password_changed');

        // Send security alert
        $this->sendSecurityAlert($user, $request, 'password_changed_manual');

        return response()->json([
            'status' => 'success',
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
    }

    /**
     * Check Authentication Status
     */
    public function checkAuth(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'authenticated' => false,
                'message' => 'غير مصرح بالوصول'
            ], 401);
        }

        // Check account status
        if ($user->status !== self::ACCOUNT_STATUS['active']) {
            return response()->json([
                'status' => 'error',
                'authenticated' => false,
                'message' => 'الحساب غير نشط',
                'account_status' => $user->status
            ], 403);
        }

        // Check token expiry
        $token = $user->currentAccessToken();
        $tokenAge = now()->diffInMinutes($token->created_at);
        $expiresIn = 720 - $tokenAge; // 12 hours - current age in minutes

        if ($expiresIn <= 30) { // 30 minutes or less remaining
            return response()->json([
                'status' => 'warning',
                'authenticated' => true,
                'message' => 'الجلسة ستنتهي قريباً',
                'session_expires_in' => $expiresIn,
                'should_refresh' => true
            ]);
        }

        return response()->json([
            'status' => 'success',
            'authenticated' => true,
            'user' => $this->prepareUserData($user),
            'permissions' => $this->getUserPermissions($user->role),
            'session_expires_in' => $expiresIn,
            'token_age' => $tokenAge,
            'is_online' => $user->is_online
        ]);
    }

    /**
     * Refresh Access Token
     */
    public function refreshToken(Request $request)
    {
        $user = $request->user();

        // Get current token
        $currentToken = $user->currentAccessToken();

        // Check if token can be refreshed (not too old)
        $tokenAge = now()->diffInMinutes($currentToken->created_at);

        if ($tokenAge > 720) { // Older than 12 hours
            return response()->json([
                'status' => 'error',
                'message' => 'لا يمكن تجديد التوكن المنتهية صلاحيته'
            ], 400);
        }

        // Revoke old token
        $currentToken->delete();

        // Create new token
        $newToken = $user->createToken(
            'MedicalAppToken',
            $this->getTokenAbilities($user->role),
            now()->addHours(12)
        )->plainTextToken;

        // Log activity
        $this->logActivity($user, 'token_refreshed');

        return response()->json([
            'status' => 'success',
            'access_token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => 43200,
            'expires_at' => now()->addHours(12)->toIso8601String()
        ]);
    }

    /**
     * Get User Profile
     */
    public function getUserProfile(Request $request)
    {
        $user = $request->user();

        $profileData = [
            'basic_info' => $this->prepareUserData($user),
            'medical_info' => $this->getMedicalInfo($user),
            'contact_info' => [
                'email' => $user->email,
                'phone' => $user->phone,
                'secondary_phone' => $user->secondary_phone,
                'address' => $user->address,
                'city' => $user->city,
                'country' => $user->country
            ],
            'activity' => [
                'last_login' => $user->last_login_at?->format('Y-m-d H:i:s'),
                'last_login_ip' => $user->last_login_ip,
                'login_count' => $user->login_count,
                'account_created' => $user->created_at->format('Y-m-d'),
                'is_online' => $user->is_online,
                'email_verified' => !is_null($user->email_verified_at),
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s')
            ],
            'security' => [
                'two_factor_enabled' => $user->two_factor_enabled,
                'last_password_change' => $user->last_password_change?->format('Y-m-d H:i:s'),
                'password_changed_at' => $user->password_changed_at?->format('Y-m-d H:i:s')
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => $profileData
        ]);
    }

    /**
     * Update User Profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        // Validation rules
        $rules = [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|regex:/^[0-9+\-\s()]{10,20}$/',
            'secondary_phone' => 'sometimes|nullable|string|max:20|regex:/^[0-9+\-\s()]{10,20}$/',
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'specialization' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'city' => 'sometimes|string|max:100',
            'country' => 'sometimes|string|max:100'
        ];

        // Add role-specific fields
        switch ($user->role) {
            case 'DOCTOR':
                $rules['medical_license'] = 'sometimes|string|max:100';
                $rules['hospital'] = 'sometimes|string|max:255';
                $rules['department'] = 'sometimes|string|max:255';
                $rules['years_experience'] = 'sometimes|integer|min:0|max:50';
                break;

            case 'PHARMACIEN':
                $rules['pharmacy_name'] = 'sometimes|string|max:255';
                $rules['pharmacy_license'] = 'sometimes|string|max:100';
                $rules['pharmacy_address'] = 'sometimes|string|max:500';
                break;
            case 'ENTREPRENEUR_PHARMACEUTIQUE':
                $rules['company_name'] = 'sometimes|string|max:255';
                $rules['business_registration'] = 'sometimes|string|max:100';
                break;
        }

        $validator = Validator::make($request->all(), $rules, [
            'phone.regex' => 'رقم الهاتف غير صالح',
            'secondary_phone.regex' => 'رقم الهاتف الثانوي غير صالح',
            'avatar.max' => 'حجم الصورة يجب أن لا يتعدى 5 ميجابايت',
            'avatar.mimes' => 'يجب أن تكون الصورة بصيغة: jpeg, png, jpg, gif, webp'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'فشل التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update basic info
        $updateData = $request->only([
            'name', 'phone', 'secondary_phone', 'specialization',
            'address', 'city', 'country'
        ]);

        // Update role-specific info
        switch ($user->role) {
            case 'DOCTOR':
                $updateData['medical_license'] = $request->medical_license;
                $updateData['hospital'] = $request->hospital;
                $updateData['department'] = $request->department;
                $updateData['years_experience'] = $request->years_experience;
                break;

            case 'PHARMACIEN':
                $updateData['pharmacy_name'] = $request->pharmacy_name;
                $updateData['pharmacy_license'] = $request->pharmacy_license;
                $updateData['pharmacy_address'] = $request->pharmacy_address;
                break;

            case 'ENTREPRENEUR_PHARMACEUTIQUE':
                $updateData['company_name'] = $request->company_name;
                $updateData['business_registration'] = $request->business_registration;
                break;
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars/' . $user->id, 'public');
            $updateData['avatar'] = $path;
        }

        // Update user
        $user->update(array_filter($updateData));

        // Log activity
        $this->logActivity($user, 'profile_updated');

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث الملف الشخصي بنجاح',
            'user' => $this->prepareUserData($user)
        ]);
    }

    /**
     * Verify Email
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'status' => 'error',
                'message' => 'رابط التفعيل غير صالح'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'info',
                'message' => 'البريد الإلكتروني مفعل مسبقاً'
            ]);
        }

        $user->markEmailAsVerified();

        // Update account status if it was pending
        if ($user->status === self::ACCOUNT_STATUS['pending']) {
            $user->update(['status' => self::ACCOUNT_STATUS['active']]);
        }

        // Log activity
        $this->logActivity($user, 'email_verified');

        return response()->json([
            'status' => 'success',
            'message' => 'تم تفعيل البريد الإلكتروني بنجاح'
        ]);
    }

    /**
     * Resend Verification Email
     */
    public function resendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'info',
                'message' => 'البريد الإلكتروني مفعل مسبقاً'
            ]);
        }

        $user->sendEmailVerificationNotification();

        // Log activity
        $this->logActivity($user, 'verification_email_resent');

        return response()->json([
            'status' => 'success',
            'message' => 'تم إرسال رابط التفعيل مرة أخرى'
        ]);
    }

    /**
     * Deactivate Account
     */
    public function deactivateAccount(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'password' => 'required|current_password',
            'reason' => 'sometimes|string|max:500'
        ], [
            'password.required' => 'كلمة المرور مطلوبة',
            'password.current_password' => 'كلمة المرور غير صحيحة'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update([
            'status' => self::ACCOUNT_STATUS['inactive'],
            'deactivated_at' => now(),
            'deactivation_reason' => $request->reason,
            'is_online' => false
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        // Log activity
        $this->logActivity($user, 'account_deactivated', [
            'reason' => $request->reason
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تعطيل الحساب بنجاح'
        ]);
    }

    /**
     * Get Active Sessions
     */
    public function getActiveSessions(Request $request)
    {
        $user = $request->user();

        $sessions = $user->tokens()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($token) use ($user) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'device' => $this->parseUserAgent($token->last_used_agent),
                    'ip_address' => $token->last_used_ip,
                    'last_used' => $token->last_used_at?->format('Y-m-d H:i:s'),
                    'created_at' => $token->created_at->format('Y-m-d H:i:s'),
                    'is_current' => $token->id === $user->currentAccessToken()?->id,
                    'abilities' => $token->abilities
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'sessions' => $sessions,
                'total_sessions' => $sessions->count(),
                'current_session_id' => $user->currentAccessToken()?->id
            ]
        ]);
    }

    /**
     * Revoke Session
     */
    public function revokeSession(Request $request, $sessionId)
    {
        $user = $request->user();

        $token = $user->tokens()->where('id', $sessionId)->first();

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'الجلسة غير موجودة'
            ], 404);
        }

        // Prevent revoking current session through this endpoint
        if ($token->id === $user->currentAccessToken()?->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'لا يمكن إنهاء الجلسة الحالية من هنا. استخدم تسجيل الخروج.'
            ], 400);
        }

        $token->delete();

        // Log activity
        $this->logActivity($user, 'session_revoked', [
            'session_id' => $sessionId
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم إنهاء الجلسة بنجاح'
        ]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Prepare user data for response
     */
    private function prepareUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'role_display' => $this->getRoleDisplayName($user->role),
            'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'avatar_url' => $user->avatar ? url('storage/' . $user->avatar) : null,
            'phone' => $user->phone,
            'specialization' => $user->specialization,
            'status' => $user->status,
            'status_display' => $this->getStatusDisplayName($user->status),
            'is_verified' => (bool) $user->email_verified_at,
            'is_online' => (bool) $user->is_online,
            'created_at' => $user->created_at->format('Y-m-d'),
            'created_at_full' => $user->created_at->toIso8601String()
        ];
    }

    /**
     * Get role display name
     */
    private function getRoleDisplayName(string $role): string
    {
        $displayNames = [
            'DOCTOR' => 'طبيب',
            'PHARMACIEN' => 'صيدلي',
            'PATIENT' => 'مريض',
            'ENTREPRENEUR_PHARMACEUTIQUE' => 'رائد أعمال صيدلاني'
        ];

        return $displayNames[$role] ?? $role;
    }

    /**
     * Get status display name
     */
    private function getStatusDisplayName(string $status): string
    {
        $displayNames = [
            'active' => 'نشط',
            'pending' => 'قيد المراجعة',
            'suspended' => 'موقوف',
            'inactive' => 'غير نشط',
            'blocked' => 'محظور'
        ];

        return $displayNames[$status] ?? $status;
    }

    /**
     * Get user permissions based on role
     */
    private function getUserPermissions(string $role): array
    {
        $permissions = [
            'DOCTOR' => [
                'view_patients',
                'prescribe_medication',
                'view_appointments',
                'manage_appointments',
                'view_medical_records',
                'create_prescriptions',
                'view_lab_results',
                'manage_availability'
            ],
            'PHARMACIEN' => [
                'manage_inventory',
                'dispense_medication',
                'view_prescriptions',
                'manage_orders',
                'view_sales',
                'manage_suppliers',
                'view_reports'
            ],
            'PATIENT' => [
                'view_profile',
                'book_appointments',
                'view_prescriptions',
                'view_medical_records',
                'view_billing',
                'manage_notifications',
                'upload_documents'
            ],
            'ENTREPRENEUR_PHARMACEUTIQUE' => [
                'manage_products',
                'view_reports',
                'manage_orders',
                'manage_suppliers',
                'view_sales',
                'manage_inventory',
                'view_analytics'
            ]
        ];

        return $permissions[$role] ?? [];
    }

    /**
     * Get token abilities based on role
     */
    private function getTokenAbilities(string $role): array
    {
        $abilities = [
            'DOCTOR' => [
                'access:dashboard',
                'access:patients',
                'access:appointments',
                'access:prescriptions',
                'access:medical-records'
            ],
            'PHARMACIEN' => [
                'access:dashboard',
                'access:inventory',
                'access:prescriptions',
                'access:orders',
                'access:sales'
            ],
            'PATIENT' => [
                'access:dashboard',
                'access:appointments',
                'access:prescriptions',
                'access:medical-records',
                'access:billing'
            ],
            'ENTREPRENEUR_PHARMACEUTIQUE' => [
                'access:dashboard',
                'access:products',
                'access:orders',
                'access:reports',
                'access:inventory'
            ]
        ];

        return $abilities[$role] ?? ['access:basic'];
    }

    /**
     * Get medical info based on role
     */
    private function getMedicalInfo(User $user): array
    {
        $info = [];

        switch ($user->role) {
            case 'DOCTOR':
                $info = [
                    'medical_license' => $user->medical_license,
                    'hospital' => $user->hospital,
                    'department' => $user->department,
                    'years_experience' => $user->years_experience,
                    'consultation_fee' => $user->consultation_fee,
                    'qualifications' => $user->qualifications
                ];
                break;

            case 'PHARMACIEN':
                $info = [
                    'pharmacy_name' => $user->pharmacy_name,
                    'pharmacy_license' => $user->pharmacy_license,
                    'pharmacy_address' => $user->pharmacy_address,
                    'working_hours' => $user->working_hours,
                    'pharmacy_phone' => $user->pharmacy_phone
                ];
                break;

            case 'ENTREPRENEUR_PHARMACEUTIQUE':
                $info = [
                    'company_name' => $user->company_name,
                    'business_registration' => $user->business_registration,
                    'company_address' => $user->company_address,
                    'tax_number' => $user->tax_number,
                    'product_categories' => $user->product_categories ? json_decode($user->product_categories, true) : []
                ];
                break;
        }

        // Remove null values
        return array_filter($info, function($value) {
            return !is_null($value) && $value !== '';
        });
    }

    /**
     * Create remember token
     */
    private function createRememberToken(User $user): string
    {
        $token = Str::random(60);
        Cache::put('remember_token_' . $user->id, $token, now()->addDays(30));
        return $token;
    }

    /**
     * Check for unusual login
     */
    private function isUnusualLogin(User $user, Request $request): bool
    {
        // Check IP change
        if ($user->last_login_ip && $user->last_login_ip !== $request->ip()) {
            return true;
        }

        // Check time of day (login outside usual hours)
        $lastLoginHour = $user->last_login_at?->hour;
        $currentHour = now()->hour;

        if ($lastLoginHour && abs($currentHour - $lastLoginHour) > 6) {
            return true;
        }

        // Check user agent change
        $lastUserAgent = Cache::get('user_agent_' . $user->id);
        $currentUserAgent = $request->userAgent();

        if ($lastUserAgent && $lastUserAgent !== $currentUserAgent) {
            Cache::put('user_agent_' . $user->id, $currentUserAgent, now()->addDays(7));
            return true;
        }

        Cache::put('user_agent_' . $user->id, $currentUserAgent, now()->addDays(7));
        return false;
    }

    /**
     * Send security alert
     */
    private function sendSecurityAlert(User $user, Request $request, string $type): void
    {
        $alerts = [
            'unusual_login' => [
                'title' => 'تسجيل دخول غير معتاد',
                'message' => 'تم تسجيل الدخول إلى حسابك من جهاز أو عنوان IP جديد.',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'location' => $this->getLocationFromIP($request->ip())
            ],
            'password_changed' => [
                'title' => 'تم تغيير كلمة المرور',
                'message' => 'تم تغيير كلمة مرور حسابك بنجاح.'
            ],
            'password_changed_manual' => [
                'title' => 'تم تغيير كلمة المرور',
                'message' => 'قمت بتغيير كلمة مرور حسابك.'
            ]
        ];

        if (isset($alerts[$type])) {
            Log::channel('security')->warning('Security alert: ' . $type, [
                'user_id' => $user->id,
                'email' => $user->email,
                'alert' => $alerts[$type],
                'timestamp' => now()->toDateTimeString()
            ]);

            // Here you can add email/SMS notification
            // Mail::to($user->email)->send(new SecurityAlertMail($alerts[$type]));
        }
    }

    /**
     * Log activity
     */
    private function logActivity(User $user, string $action, array $properties = []): void
    {
        $logData = [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'action' => $action,
            'timestamp' => now()->toDateTimeString(),
            'properties' => $properties
        ];

        Log::channel('auth')->info('User activity', $logData);

        // If using Laravel Activity Log package
        if (class_exists('Spatie\Activitylog\ActivitylogServiceProvider')) {
            activity()
                ->causedBy($user)
                ->withProperties($properties)
                ->log($action);
        }
    }

    /**
     * Get location from IP
     */
    private function getLocationFromIP(string $ip): array
    {
        // This is a simplified version. In production, use a service like ipinfo.io
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return [
                'country' => 'Local',
                'city' => 'Localhost',
                'region' => 'Development'
            ];
        }

        // For production, you might want to use:
        // $response = Http::get("http://ipinfo.io/{$ip}/json");
        // return $response->json();

        return [
            'country' => 'Unknown',
            'city' => 'Unknown',
            'region' => 'Unknown'
        ];
    }

    /**
     * Parse user agent string
     */
    private function parseUserAgent(?string $userAgent): array
    {
        if (!$userAgent) {
            return [
                'browser' => 'Unknown',
                'platform' => 'Unknown',
                'device' => 'Unknown'
            ];
        }

        // Simple parsing - in production use a package like jenssegers/agent
        $device = 'Desktop';
        $platform = 'Unknown';
        $browser = 'Unknown';

        if (strpos($userAgent, 'Mobile') !== false) {
            $device = 'Mobile';
        } elseif (strpos($userAgent, 'Tablet') !== false) {
            $device = 'Tablet';
        }

        if (strpos($userAgent, 'Windows') !== false) {
            $platform = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $platform = 'Mac';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $platform = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $platform = 'Android';
        } elseif (strpos($userAgent, 'iOS') !== false) {
            $platform = 'iOS';
        }

        if (strpos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            $browser = 'Edge';
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
            'device' => $device,
            'raw' => $userAgent
        ];
    }

    /**
     * Check if password is in history
     */
    private function isPasswordInHistory(User $user, string $newPassword): bool
    {
        $history = Cache::get('password_history_' . $user->id, []);

        foreach ($history as $oldPasswordHash) {
            if (Hash::check($newPassword, $oldPasswordHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add password to history
     */
    private function addToPasswordHistory(User $user, string $password): void
    {
        $history = Cache::get('password_history_' . $user->id, []);

        // Keep only last 5 passwords
        $history[] = Hash::make($password);
        if (count($history) > 5) {
            array_shift($history);
        }

        Cache::put('password_history_' . $user->id, $history, now()->addDays(90));
    }
}
