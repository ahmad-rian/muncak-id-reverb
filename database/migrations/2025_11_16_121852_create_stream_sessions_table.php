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
        Schema::create('stream_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_id')->constrained()->onDelete('cascade');
            $table->integer('duration_seconds')->default(0);
            $table->bigInteger('bytes_sent')->default(0);
            $table->integer('avg_bitrate')->nullable(); // kbps
            $table->integer('peak_viewers')->default(0);
            $table->timestamp('session_started_at');
            $table->timestamp('session_ended_at')->nullable();
            $table->timestamps();

            $table->index('stream_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stream_sessions');
    }
};
