 <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('specialty', 100);
            $table->string('sub_specialty', 100)->nullable();
            $table->string('license_number')->unique();
            $table->text('bio')->nullable();
            $table->json('working_hours')->nullable();
            $table->decimal('consultation_fee', 8, 2)->default(0);
            $table->unsignedInteger('consultation_duration')->default(30);
            $table->boolean('is_available')->default(true);
            $table->json('languages')->nullable();
            $table->json('education')->nullable();
            $table->json('experience')->nullable();
            $table->string('clinic_name')->nullable();
            $table->text('clinic_address')->nullable();
            $table->string('clinic_city', 100)->nullable();
            $table->decimal('rating', 3, 1)->default(0);
            $table->unsignedInteger('total_reviews')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_profiles');
    }
};