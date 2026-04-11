<?php

namespace App\Http\Controllers\API\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PatientAppointmentController extends Controller
{
    /**
     * عرض مواعيد المريض
     */
    public function index(Request $request)
    {
        try {
            $patient = $request->user()->patient;

            $query = Appointment::with('doctor')
                ->where('patient_id', $patient->id);

            // تطبيق الفلاتر
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('date_range')) {
                switch ($request->date_range) {
                    case 'today':
                        $query->whereDate('appointment_datetime', today());
                        break;
                    case 'upcoming':
                        $query->upcoming();
                        break;
                    case 'past':
                        $query->past();
                        break;
                }
            }

            $appointments = $query->orderBy('appointment_datetime', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $appointments->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,
                        'doctor' => [
                            'id' => $appointment->doctor->id,
                            'name' => $appointment->doctor->full_name,
                            'specialty' => $appointment->doctor->specialty,
                            'avatar' => $appointment->doctor->avatar,
                            'rating' => $appointment->doctor->rating,
                        ],
                        'date' => $appointment->formatted_date,
                        'time' => $appointment->formatted_time,
                        'type' => $appointment->type,
                        'status' => $appointment->status,
                        'location' => $appointment->location ?? ($appointment->type === 'Video'
                            ? 'Virtual Consultation'
                            : $appointment->doctor->hospital),
                        'reason' => $appointment->reason,
                        'notes' => $appointment->notes,
                        'fee' => $appointment->fee,
                        'can_cancel' => $appointment->canBeCancelled(),
                        'can_reschedule' => $appointment->canBeRescheduled(),
                    ];
                }),
                'pagination' => [
                    'total' => $appointments->total(),
                    'per_page' => $appointments->perPage(),
                    'current_page' => $appointments->currentPage(),
                    'last_page' => $appointments->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('PatientAppointmentController@index failed', ['error' => $e->getMessage(), 'user_id' => $request->user()->id ?? null]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve appointments.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * حجز موعد جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doctor_id' => 'required|exists:doctors,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'type' => 'required|in:In-person,Video',
            'reason' => 'nullable|string|max:255',
            'symptoms' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $patient = $request->user()->patient;
        $doctor = Doctor::find($request->doctor_id);

        if (!$doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found'
            ], 404);
        }

        // التحقق من توفر الطبيب
        if (!$doctor->is_available) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor is not available at the moment'
            ], 400);
        }

        // التحقق من توفر الوقت
        $existingAppointment = Appointment::where('doctor_id', $doctor->id)
            ->where('appointment_datetime', $request->appointment_date . ' ' . $request->appointment_time)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->exists();

        if ($existingAppointment) {
            return response()->json([
                'success' => false,
                'message' => 'This time slot is already booked'
            ], 400);
        }

        // التحقق من أن الوقت ضمن ساعات العمل
        $date = \Carbon\Carbon::parse($request->appointment_date);
        if (!$doctor->isAvailableOn($date, $request->appointment_time)) {
            return response()->json([
                'success' => false,
                'message' => 'Selected time is outside doctor\'s working hours'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // إنشاء الموعد
            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'appointment_datetime' => $request->appointment_date . ' ' . $request->appointment_time,
                'type' => $request->type,
                'status' => 'scheduled',
                'reason' => $request->reason,
                'symptoms' => $request->symptoms,
                'notes' => $request->notes,
                'fee' => $doctor->consultation_fee,
                'duration_minutes' => 30,
            ]);

            // تحديث time slot
            // Consider using an Eloquent model for TimeSlot if it exists for better maintainability
            DB::table('time_slots')->updateOrInsert(
                [
                    'doctor_id' => $doctor->id,
                    'slot_date' => $request->appointment_date,
                    'start_time' => $request->appointment_time,
                ],
                ['status' => 'booked']
            );

            // إنشاء إشعار للطبيب
            // Consider using an Eloquent model for Notification and dispatching an event
            DB::table('notifications')->insert([
                'user_id' => $doctor->user_id,
                'title' => 'New Appointment',
                'message' => "New appointment booked by {$patient->user->name} on {$appointment->formatted_date} at {$appointment->formatted_time}",
                'type' => 'appointment_reminder',
                'data' => json_encode(['appointment_id' => $appointment->id]),
                'action_url' => "/doctor/appointments/{$appointment->id}",
                'action_text' => 'View Appointment',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Appointment booked successfully',
                'data' => [
                    'id' => $appointment->id,
                    'doctor' => [
                        'name' => $doctor->full_name,
                        'specialty' => $doctor->specialty,
                        'avatar' => $doctor->avatar,
                        'rating' => $doctor->rating,
                    ],
                    'date' => $appointment->formatted_date,
                    'time' => $appointment->formatted_time,
                    'type' => $appointment->type,
                    'status' => $appointment->status,
                    'fee' => $appointment->fee,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PatientAppointmentController@store failed', ['error' => $e->getMessage(), 'user_id' => $request->user()->id ?? null]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to book appointment. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * إلغاء موعد
     */
    public function cancel(Request $request, $id)
    {
        $patient = $request->user()->patient;

        $appointment = Appointment::where('patient_id', $patient->id)
            ->findOrFail($id);

        if (!$appointment->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'This appointment cannot be cancelled'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $oldStatus = $appointment->status;
            $appointment->update(['status' => 'cancelled']);

            // تحرير time slot
            // Consider using an Eloquent model for TimeSlot if it exists for better maintainability
            DB::table('time_slots')->where('doctor_id', $appointment->doctor->id)
                ->whereDate('slot_date', $appointment->appointment_datetime->format('Y-m-d')) // Use Carbon for date format
                ->whereTime('start_time', $appointment->appointment_datetime->format('H:i:s')) // Use Carbon for time format
                ->update(['status' => 'available']);

            // إنشاء إشعار للطبيب
            // Consider using an Eloquent model for Notification and dispatching an event
            DB::table('notifications')->insert([
                'user_id' => $appointment->doctor->user_id,
                'title' => 'Appointment Cancelled',
                'message' => "Appointment with {$patient->user->name} on {$appointment->formatted_date} has been cancelled",
                'type' => 'appointment_reminder',
                'data' => json_encode(['appointment_id' => $appointment->id]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Appointment cancelled successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PatientAppointmentController@cancel failed', ['error' => $e->getMessage(), 'appointment_id' => $id, 'user_id' => $request->user()->id ?? null]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel appointment. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * تفاصيل موعد
     */
    public function show(Request $request, $id)
    {
        $patient = $request->user()->patient;

        $appointment = Appointment::with('doctor', 'prescription', 'medicalRecord')
            ->where('patient_id', $patient->id)
            ->findOrFail($id);

        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $appointment->id,
                    'doctor' => [
                        'id' => $appointment->doctor->id,
                        'name' => $appointment->doctor->full_name,
                        'specialty' => $appointment->doctor->specialty,
                        'avatar' => $appointment->doctor->avatar,
                        'rating' => $appointment->doctor->rating,
                        'phone' => $appointment->doctor->user->phone,
                        'email' => $appointment->doctor->user->email,
                        'hospital' => $appointment->doctor->hospital,
                    ],
                    'date' => $appointment->formatted_date,
                    'time' => $appointment->formatted_time,
                    'type' => $appointment->type,
                    'status' => $appointment->status,
                    'reason' => $appointment->reason,
                    'symptoms' => $appointment->symptoms,
                    'diagnosis' => $appointment->diagnosis,
                    'notes' => $appointment->notes,
                    'fee' => $appointment->fee,
                    'prescription' => $appointment->prescription,
                    'medical_record' => $appointment->medicalRecord,
                    'created_at' => $appointment->created_at->format('Y-m-d H:i:s'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('PatientAppointmentController@show failed', ['error' => $e->getMessage(), 'appointment_id' => $id, 'user_id' => $request->user()->id ?? null]);
            return response()->json(['success' => false, 'message' => 'Appointment not found or failed to retrieve details.', 'error' => config('app.debug') ? $e->getMessage() : null], 404);
        }
    }
}
