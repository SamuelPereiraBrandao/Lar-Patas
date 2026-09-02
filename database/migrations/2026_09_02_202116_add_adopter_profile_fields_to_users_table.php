<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('city')->nullable()->after('phone');
            $table->string('state', 2)->nullable()->after('city');
            $table->date('birth_date')->nullable()->after('state');
            $table->string('housing_type')->nullable()->after('birth_date');
            $table->boolean('has_other_pets')->default(false)->after('housing_type');
            $table->text('household_description')->nullable()->after('has_other_pets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'city', 'state', 'birth_date', 'housing_type', 'has_other_pets', 'household_description']);
        });
    }
};
