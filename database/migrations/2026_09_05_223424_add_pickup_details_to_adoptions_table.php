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
        Schema::table('adoptions', function (Blueprint $table) {
            $table->timestamp('pickup_at')->nullable();
            $table->string('pickup_timezone', 64)->nullable();
            $table->string('pickup_location', 500)->nullable();
            $table->text('pickup_message')->nullable();
            $table->text('pickup_code')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adoptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheduled_by');
            $table->dropConstrainedForeignId('released_by');
            $table->dropColumn(['pickup_at', 'pickup_timezone', 'pickup_location', 'pickup_message', 'pickup_code', 'released_at']);
        });
    }
};
