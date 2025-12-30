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
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('cloudinary_public_id')->nullable()->after('file_extension');
            $table->text('cloudinary_url')->nullable()->after('cloudinary_public_id');
            $table->index('cloudinary_public_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex(['cloudinary_public_id']);
            $table->dropColumn(['cloudinary_public_id', 'cloudinary_url']);
        });
    }
};
