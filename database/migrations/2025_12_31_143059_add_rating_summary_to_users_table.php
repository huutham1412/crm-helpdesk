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
            $table->decimal('avg_rating', 3, 2)->nullable()->after('avatar');
            $table->unsignedInteger('total_ratings')->default(0)->after('avg_rating');
            $table->json('rating_distribution')->nullable()->after('total_ratings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avg_rating', 'total_ratings', 'rating_distribution']);
        });
    }
};
