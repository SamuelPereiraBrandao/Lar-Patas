<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['profile_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_post_likes');
    }
};
