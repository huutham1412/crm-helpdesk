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
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('last_status_change_at')->nullable()->after('closed_at');
            $table->timestamp('first_escalated_at')->nullable()->after('last_status_change_at');
            $table->boolean('is_escalated')->default(false)->after('first_escalated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['last_status_change_at', 'first_escalated_at', 'is_escalated']);
        });
    }
};
