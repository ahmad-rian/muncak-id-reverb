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
        // Streams Table Indexes - Performance optimization
        Schema::table('streams', function (Blueprint $table) {
            $table->index('status');
            $table->index('viewer_count');
            $table->index('started_at');
            $table->index('jalur_id');
            $table->index('mountain_id');
            $table->index(['status', 'viewer_count']); // Composite for filtering
            $table->index(['status', 'started_at']); // Composite for ordering live streams
        });

        // Chat Messages Table Indexes
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->index('stream_id');
            $table->index('created_at');
            $table->index(['stream_id', 'created_at']); // Composite for chat history queries
        });

        // Trail Classifications Table Indexes
        Schema::table('trail_classifications', function (Blueprint $table) {
            $table->index('stream_id');
            $table->index('classified_at');
            $table->index(['stream_id', 'classified_at']); // Composite for latest classification
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['viewer_count']);
            $table->dropIndex(['started_at']);
            $table->dropIndex(['jalur_id']);
            $table->dropIndex(['mountain_id']);
            $table->dropIndex(['status', 'viewer_count']);
            $table->dropIndex(['status', 'started_at']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['stream_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['stream_id', 'created_at']);
        });

        Schema::table('trail_classifications', function (Blueprint $table) {
            $table->dropIndex(['stream_id']);
            $table->dropIndex(['classified_at']);
            $table->dropIndex(['stream_id', 'classified_at']);
        });
    }
};
