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
            // Check if index exists before creating
            if (!$this->indexExists('streams', 'streams_status_index')) {
                $table->index('status');
            }
            if (!$this->indexExists('streams', 'streams_viewer_count_index')) {
                $table->index('viewer_count');
            }
            if (!$this->indexExists('streams', 'streams_started_at_index')) {
                $table->index('started_at');
            }
            if (!$this->indexExists('streams', 'streams_jalur_id_index')) {
                $table->index('jalur_id');
            }
            if (!$this->indexExists('streams', 'streams_mountain_id_index')) {
                $table->index('mountain_id');
            }
            if (!$this->indexExists('streams', 'streams_status_viewer_count_index')) {
                $table->index(['status', 'viewer_count']);
            }
            if (!$this->indexExists('streams', 'streams_status_started_at_index')) {
                $table->index(['status', 'started_at']);
            }
        });

        // Chat Messages Table Indexes
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!$this->indexExists('chat_messages', 'chat_messages_stream_id_index')) {
                $table->index('stream_id');
            }
            if (!$this->indexExists('chat_messages', 'chat_messages_created_at_index')) {
                $table->index('created_at');
            }
            if (!$this->indexExists('chat_messages', 'chat_messages_stream_id_created_at_index')) {
                $table->index(['stream_id', 'created_at']);
            }
        });

        // Trail Classifications Table Indexes
        Schema::table('trail_classifications', function (Blueprint $table) {
            if (!$this->indexExists('trail_classifications', 'trail_classifications_stream_id_index')) {
                $table->index('stream_id');
            }
            if (!$this->indexExists('trail_classifications', 'trail_classifications_classified_at_index')) {
                $table->index('classified_at');
            }
            if (!$this->indexExists('trail_classifications', 'trail_classifications_stream_id_classified_at_index')) {
                $table->index(['stream_id', 'classified_at']);
            }
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
        $doctrineTable = $doctrineSchemaManager->introspectTable($table);

        return $doctrineTable->hasIndex($index);
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
