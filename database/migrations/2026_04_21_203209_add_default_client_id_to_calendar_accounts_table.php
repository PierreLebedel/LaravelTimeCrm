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
        Schema::table('calendar_accounts', function (Blueprint $table) {
            $table->foreignId('default_client_id')->nullable()->after('password')->constrained('clients')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendar_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_client_id');
        });
    }
};
