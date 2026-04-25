 <?php
// database/migrations/xxxx_create_medications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacist_id')->constrained('users')->cascadeOnDelete();
            $table->string('medication_name');
            $table->string('category');
            $table->integer('stock_quantity')->default(0);
            $table->integer('reorder_level')->default(10);
            $table->date('expiry_date');
            $table->decimal('price', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('requires_prescription')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pharmacist_id', 'category']);
            $table->index(['pharmacist_id', 'stock_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
