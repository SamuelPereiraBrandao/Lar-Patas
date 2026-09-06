<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'pet_id']);
        });
        Schema::create('saved_searches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->json('filters');
            $t->timestamps();
        });
        Schema::create('user_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'blocked_user_id']);
        });
        Schema::create('content_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('profile_post_id')->constrained()->cascadeOnDelete();
            $t->string('reason', 1000);
            $t->string('status', 20)->default('pending')->index();
            $t->text('resolution')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'profile_post_id']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('resource');
            $t->json('changes')->nullable();
            $t->timestamps();
        });
        Schema::create('pet_health_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind', 30);
            $t->string('title', 120);
            $t->date('performed_at')->nullable();
            $t->date('due_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::table('profile_posts', fn (Blueprint $t) => $t->timestamp('hidden_at')->nullable());
        Schema::table('adoptions', function (Blueprint $t) {
            foreach (['cancelled_at', 'reschedule_requested_at', 'requested_pickup_at', 'reminded_at', 'followup_sent_at', 'followup_completed_at'] as $column) {
                $t->timestamp($column)->nullable();
            }
            foreach (['cancellation_reason', 'reschedule_reason', 'adaptation_notes'] as $column) {
                $t->text($column)->nullable();
            }
            $t->string('adaptation_status', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('adoptions', fn (Blueprint $t) => $t->dropColumn(['cancelled_at', 'reschedule_requested_at', 'requested_pickup_at', 'reminded_at', 'followup_sent_at', 'followup_completed_at', 'cancellation_reason', 'reschedule_reason', 'adaptation_notes', 'adaptation_status']));
        Schema::table('profile_posts', fn (Blueprint $t) => $t->dropColumn('hidden_at'));
        foreach (['pet_health_records', 'audit_logs', 'content_reports', 'user_blocks', 'saved_searches', 'pet_favorites'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
