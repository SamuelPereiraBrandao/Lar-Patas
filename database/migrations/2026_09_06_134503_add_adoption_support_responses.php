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
        Schema::table('adoptions', function (Blueprint $table): void {
            $table->text('support_message')->nullable();
            $table->timestamp('support_replied_at')->nullable();
            $table->timestamp('followup_resolved_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adoptions', fn (Blueprint $table) => $table->dropColumn(['support_message', 'support_replied_at', 'followup_resolved_at']));
    }
};
