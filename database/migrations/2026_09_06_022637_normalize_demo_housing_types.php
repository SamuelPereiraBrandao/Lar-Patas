<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['Casa com quintal cercado' => 'Casa com quintal', 'Apartamento com telas' => 'Apartamento'] as $previous => $valid) {
            DB::table('users')->where('housing_type', $previous)->update(['housing_type' => $valid]);
            DB::table('adoptions')->where('housing_type', $previous)->update(['housing_type' => $valid]);
        }
    }

    /**
     * Normalized values cannot be distinguished from previously valid values.
     */
    public function down(): void {}
};
