<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_caretakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted'])->default('pending');
            $table->timestamps();
            $table->unique(['pet_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_caretakers');
    }
};
