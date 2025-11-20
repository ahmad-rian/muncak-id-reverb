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
        Schema::create('trail_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_id')->constrained('streams')->onDelete('cascade');
            $table->string('trail_name');
            $table->timestamp('classified_at');
            $table->enum('weather', ['cerah', 'berawan', 'hujan'])->nullable();
            $table->enum('crowd_density', ['sepi', 'sedang', 'ramai'])->nullable();
            $table->enum('visibility', ['jelas', 'kabut_sedang', 'kabut_tebal'])->nullable();
            $table->float('confidence_weather')->nullable();
            $table->float('confidence_crowd')->nullable();
            $table->float('confidence_visibility')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index(['stream_id', 'classified_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trail_classifications');
    }
};
