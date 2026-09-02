<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->foreignId('shelter_id')->nullable()->after('owner_id')->constrained()->nullOnDelete();
        });

        $shelterId = DB::table('shelters')->insertGetId([
            'name' => 'Lar & Patas — Sede Jaraguá',
            'district' => 'Jaraguá',
            'city' => 'São Paulo',
            'state' => 'SP',
            'address' => 'Jaraguá, São Paulo - SP',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pets')->whereNull('shelter_id')->update(['shelter_id' => $shelterId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shelter_id');
        });
    }
};
