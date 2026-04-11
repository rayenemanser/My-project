<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppNotification;
use App\Models\DoctorProfile;
use App\Models\MedicalRecord;
use App\Models\PatientProfile;
use App\Models\Prescription;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Doctors ───────────────────────────────────────────────────────
        $doctors = [
            [
                'user' => [
                    'name'         => 'Ahmed Benali',
                    'email'        => 'doctor@docdz.dz',
                    'password'     => Hash::make('password'),
                    'role'         => 'DOCTOR',
                    'phone'        => '0550123456',
                    'gender'       => 'male',
                    'date_of_birth'=> '1980-05-15',
                    'city'         => 'Alger',
                    'status'       => 'active',
                ],
                'profile' => [
                    'specialty'             => 'Cardiologie',
                    'sub_specialty'         => 'Cardiologie interventionnelle',
                    'license_number'        => 'MED-ALG-001',
                    'bio'                   => 'Cardiologue spécialisé avec 15 ans d\'expérience.',
                    'clinic_name'           => 'Cabinet Dr. Benali',
                    'clinic_address'        => '12 Rue Didouche Mourad',
                    'clinic_city'           => 'Alger',
                    'consultation_fee'      => 2000,
                    'consultation_duration' => 30,
                    'is_available'          => true,
                    'languages'             => ['Arabe', 'Français'],
                    'working_hours'         => [
                        'sunday'    => ['start' => '08:00', 'end' => '12:00'],
                        'monday'    => ['start' => '08:00', 'end' => '17:00'],
                        'tuesday'   => ['start' => '08:00', 'end' => '17:00'],
                        'wednesday' => ['start' => '08:00', 'end' => '17:00'],
                        'thursday'  => ['start' => '08:00', 'end' => '12:00'],
                    ],
                ],
            ],
            [
                'user' => [
                    'name'         => 'Fatima Merzouk',
                    'email'        => 'doctor2@docdz.dz',
                    'password'     => Hash::make('password'),
                    'role'         => 'DOCTOR',
                    'phone'        => '0661234567',
                    'gender'       => 'female',
                    'date_of_birth'=> '1985-09-20',
                    'city'         => 'Oran',
                    'status'       => 'active',
                ],
                'profile' => [
                    'specialty'             => 'Pédiatrie',
                    'license_number'        => 'MED-ORA-002',
                    'bio'                   => 'Pédiatre dévouée, spécialisée dans le suivi de croissance.',
                    'clinic_name'           => 'Cabinet Dr. Merzouk',
                    'clinic_address'        => '5 Boulevard Millénium',
                    'clinic_city'           => 'Oran',
                    'consultation_fee'      => 1500,
                    'consultation_duration' => 20,
                    'is_available'          => true,
                    'languages'             => ['Arabe', 'Français', 'Anglais'],
                    'working_hours'         => [
                        'monday'    => ['start' => '09:00', 'end' => '17:00'],
                        'tuesday'   => ['start' => '09:00', 'end' => '17:00'],
                        'thursday'  => ['start' => '09:00', 'end' => '13:00'],
                        'saturday'  => ['start' => '09:00', 'end' => '12:00'],
                    ],
                ],
            ],
            [
                'user' => [
                    'name'         => 'Karim Bouzidi',
                    'email'        => 'doctor3@docdz.dz',
                    'password'     => Hash::make('password'),
                    'role'         => 'DOCTOR',
                    'phone'        => '0770987654',
                    'gender'       => 'male',
                    'date_of_birth'=> '1978-03-12',
                    'city'         => 'Sétif',
                    'status'       => 'active',
                ],
                'profile' => [
                    'specialty'             => 'Médecine Générale',
                    'license_number'        => 'MED-SET-003',
                    'bio'                   => 'Médecin généraliste, votre premier recours.',
                    'clinic_name'           => 'Cabinet Dr. Bouzidi',
                    'clinic_address'        => '8 Rue Larbi Ben Mhidi',
                    'clinic_city'           => 'Sétif',
                    'consultation_fee'      => 1000,
                    'consultation_duration' => 15,
                    'is_available'          => true,
                    'languages'             => ['Arabe', 'Français'],
                    'working_hours'         => [
                        'sunday'    => ['start' => '08:00', 'end' => '16:00'],
                        'monday'    => ['start' => '08:00', 'end' => '16:00'],
                        'tuesday'   => ['start' => '08:00', 'end' => '16:00'],
                        'wednesday' => ['start' => '08:00', 'end' => '16:00'],
                        'thursday'  => ['start' => '08:00', 'end' => '12:00'],
                    ],
                ],
            ],
        ];

        $doctorUsers = [];
        foreach ($doctors as $d) {
            $user = User::firstOrCreate(['email' => $d['user']['email']], $d['user']);
            DoctorProfile::firstOrCreate(
                ['user_id' => $user->id],
                array_merge($d['profile'], ['user_id' => $user->id])
            );
            $doctorUsers[] = $user;
        }

        // ── Patients ──────────────────────────────────────────────────────
        $patientsData = [
            [
                'user' => [
                    'name'         => 'Youcef Hadj',
                    'email'        => 'patient@docdz.dz',
                    'password'     => Hash::make('password'),
                    'role'         => 'PATIENT',
                    'phone'        => '0555111222',
                    'gender'       => 'male',
                    'date_of_birth'=> '1990-07-22',
                    'city'         => 'Alger',
                    'status'       => 'active',
                ],
                'profile' => [
                    'blood_type'              => 'A+',
                    'height'                  => 178,
                    'weight'                  => 75,
                    'allergies'               => ['Pénicilline'],
                    'chronic_conditions'      => ['Hypertension artérielle'],
                    'emergency_contact_name'  => 'Amina Hadj',
                    'emergency_contact_phone' => '0555111333',
                    'emergency_contact_relation' => 'Épouse',
                    'primary_doctor_id'       => null,
                ],
            ],
            [
                'user' => [
                    'name'         => 'Nadia Khelifi',
                    'email'        => 'patient2@docdz.dz',
                    'password'     => Hash::make('password'),
                    'role'         => 'PATIENT',
                    'phone'        => '0661222333',
                    'gender'       => 'female',
                    'date_of_birth'=> '1995-11-08',
                    'city'         => 'Oran',
                    'status'       => 'active',
                ],
                'profile' => [
                    'blood_type'         => 'O+',
                    'height'             => 165,
                    'weight'             => 60,
                    'allergies'          => [],
                    'chronic_conditions' => [],
                ],
            ],
        ];

        $patientUsers = [];
        foreach ($patientsData as $p) {
            $user = User::firstOrCreate(['email' => $p['user']['email']], $p['user']);
            $profileData = $p['profile'];
            if (isset($profileData['primary_doctor_id']) && $profileData['primary_doctor_id'] === null) {
                $profileData['primary_doctor_id'] = $doctorUsers[0]->id;
            }
            PatientProfile::firstOrCreate(
                ['user_id' => $user->id],
                array_merge($profileData, ['user_id' => $user->id])
            );
            $patientUsers[] = $user;
        }

        // ── Appointments ──────────────────────────────────────────────────
        $appointmentsData = [
            [
                'patient_id'       => $patientUsers[0]->id,
                'doctor_id'        => $doctorUsers[0]->id,
                'appointment_date' => today()->toDateString(),
                'appointment_time' => '09:00',
                'reason'           => 'Contrôle tension artérielle',
                'status'           => 'confirmed',
                'type'             => 'in_person',
                'confirmed_at'     => now(),
                'duration'         => 30,
            ],
            [
                'patient_id'       => $patientUsers[0]->id,
                'doctor_id'        => $doctorUsers[0]->id,
                'appointment_date' => today()->addDays(3)->toDateString(),
                'appointment_time' => '10:00',
                'reason'           => 'Résultats ECG',
                'status'           => 'pending',
                'type'             => 'in_person',
                'duration'         => 30,
            ],
            [
                'patient_id'       => $patientUsers[1]->id,
                'doctor_id'        => $doctorUsers[1]->id,
                'appointment_date' => today()->subDays(5)->toDateString(),
                'appointment_time' => '11:00',
                'reason'           => 'Consultation pédiatrique',
                'status'           => 'completed',
                'type'             => 'in_person',
                'confirmed_at'     => now()->subDays(5),
                'completed_at'     => now()->subDays(5),
                'duration'         => 20,
            ],
        ];

        $appointments = [];
        foreach ($appointmentsData as $a) {
            $appointments[] = Appointment::firstOrCreate(
                [
                    'patient_id'       => $a['patient_id'],
                    'doctor_id'        => $a['doctor_id'],
                    'appointment_date' => $a['appointment_date'],
                    'appointment_time' => $a['appointment_time'],
                ],
                $a
            );
        }

        // ── Prescription ──────────────────────────────────────────────────
        Prescription::firstOrCreate(
            ['patient_id' => $patientUsers[0]->id, 'doctor_id' => $doctorUsers[0]->id, 'medication' => 'Paracétamol 1g'],
            [
                'appointment_id'    => $appointments[0]->id,
                'medication'        => 'Paracétamol 1g',
                'dosage'            => '1g',
                'frequency'         => '3 fois par jour',
                'duration'          => '5 jours',
                'instructions'      => 'Prendre avec un grand verre d\'eau',
                'refills_total'     => 2,
                'refills_remaining' => 2,
                'prescribed_date'   => today()->toDateString(),
                'expiry_date'       => today()->addMonths(3)->toDateString(),
                'status'            => 'active',
            ]
        );

        // ── Medical Record ────────────────────────────────────────────────
        MedicalRecord::firstOrCreate(
            ['patient_id' => $patientUsers[1]->id, 'doctor_id' => $doctorUsers[1]->id],
            [
                'appointment_id'        => $appointments[2]->id,
                'record_type'           => 'doctor_note',
                'title'                 => 'Bilan de croissance',
                'description'           => 'Croissance normale pour l\'âge. Vitamine D recommandée.',
                'record_date'           => today()->subDays(5)->toDateString(),
                'status'                => 'normal',
                'is_visible_to_patient' => true,
            ]
        );

        // ── Review ────────────────────────────────────────────────────────
        Review::firstOrCreate(
            ['appointment_id' => $appointments[2]->id],
            [
                'patient_id'   => $patientUsers[1]->id,
                'doctor_id'    => $doctorUsers[1]->id,
                'rating'       => 5,
                'comment'      => 'Excellent médecin, très à l\'écoute.',
                'is_anonymous' => false,
            ]
        );

        // ── Notifications ─────────────────────────────────────────────────
        AppNotification::firstOrCreate(
            ['user_id' => $patientUsers[0]->id, 'type' => 'appointment_confirmed'],
            [
                'user_id' => $patientUsers[0]->id,
                'type'    => 'appointment_confirmed',
                'title'   => 'Rendez-vous confirmé',
                'message' => 'Votre rendez-vous du ' . today()->format('d/m/Y') . ' à 09:00 a été confirmé.',
                'data'    => ['appointment_id' => $appointments[0]->id],
                'is_read' => false,
            ]
        );

        $this->command->info('');
        $this->command->info('✅  Doc DZ — Seeder terminé avec succès!');
        $this->command->info('');
        $this->command->info('  🩺  DOCTORS:');
        $this->command->info('      doctor@docdz.dz   / password  → Cardiologue / Alger');
        $this->command->info('      doctor2@docdz.dz  / password  → Pédiatre / Oran');
        $this->command->info('      doctor3@docdz.dz  / password  → Médecin Général / Sétif');
        $this->command->info('');
        $this->command->info('  👤  PATIENTS:');
        $this->command->info('      patient@docdz.dz  / password');
        $this->command->info('      patient2@docdz.dz / password');
        $this->command->info('');
    }
}