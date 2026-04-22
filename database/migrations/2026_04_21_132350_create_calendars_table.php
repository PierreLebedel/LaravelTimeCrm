<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_account_id')->constrained()->restrictOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->string('color')->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('is_selected')->default(true);
            $table->timestamps();

            $table->unique(['calendar_account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendars');
    }
};
