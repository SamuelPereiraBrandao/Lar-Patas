<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')->where('name', 'receiver')->update(['name' => 'adopter', 'label' => 'Adotante', 'updated_at' => now()]);

        foreach ([['admin', 'Administrador'], ['adopter', 'Adotante'], ['donor', 'Doador']] as [$name, $label]) {
            DB::table('roles')->updateOrInsert(['name' => $name], ['label' => $label, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')->where('name', 'adopter')->update(['name' => 'receiver', 'label' => 'Receptor', 'updated_at' => now()]);
        }
    }
};
