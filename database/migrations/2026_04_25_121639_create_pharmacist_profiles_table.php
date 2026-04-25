 <?php
// database/migrations/xxxx_create_pharmacist_profiles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacist_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('pharmacy_name');
            $table->string('license_number')->unique();
            $table->string('pharmacist_license')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('wilaya')->nullable();
            $table->text('qualifications')->nullable();
            $table->integer('experience_years')->default(0);
            $table->text('certifications')->nullable();
            $table->text('insurance_accepted')->nullable();
            $table->text('specialized_equipment')->nullable();
            $table->text('additional_notes')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacist_profiles');
    }
};
