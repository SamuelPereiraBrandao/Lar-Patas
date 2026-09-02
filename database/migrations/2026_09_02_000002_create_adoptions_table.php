<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adoptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('applicant_name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('housing_type');
            $table->boolean('has_other_pets')->default(false);
            $table->text('message');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->index(['status', 'pet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adoptions');
    }
};
