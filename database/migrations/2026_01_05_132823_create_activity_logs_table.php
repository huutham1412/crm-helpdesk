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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('impersonated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->enum('log_level', ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])->default('info');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('tags')->nullable();
            $table->json('properties')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['action']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['created_at']);
            $table->index(['log_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
