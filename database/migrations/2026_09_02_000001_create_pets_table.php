<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('species', ['dog', 'cat']);
            $table->string('breed')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('size', ['small', 'medium', 'large']);
            $table->enum('sex', ['male', 'female']);
            $table->string('city');
            $table->string('temperament');
            $table->text('description');
            $table->enum('status', ['available', 'in_process', 'adopted'])->default('available');
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->index(['status', 'species']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
