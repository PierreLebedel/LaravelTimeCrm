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
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('external_id');
            $table->string('external_etag')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('feature_description')->nullable();
            $table->string('sync_status')->default('synced');
            $table->string('format_status')->default('needs_review');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['calendar_id', 'external_id']);
            $table->index(['starts_at', 'ends_at']);
            $table->index('format_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
