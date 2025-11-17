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
        Schema::table('streams', function (Blueprint $table) {
            $table->foreignId('mountain_id')->nullable()->after('user_id')->constrained('gunung')->onDelete('set null');
            $table->text('description')->nullable()->after('title');
            $table->string('stream_key', 32)->unique()->after('description');
            $table->string('location')->nullable()->after('stream_key');
            $table->string('thumbnail_url')->nullable()->after('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            $table->dropForeign(['mountain_id']);
            $table->dropColumn(['mountain_id', 'description', 'stream_key', 'location', 'thumbnail_url']);
        });
    }
};
