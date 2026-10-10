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
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('distance_km', 8, 2)->nullable()->after('price');
            $table->string('distance_status')->nullable()->after('distance_km');
            $table->string('distance_note')->nullable()->after('distance_status');
            $table->timestamp('distance_calculated_at')->nullable()->after('distance_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['distance_km', 'distance_status', 'distance_note', 'distance_calculated_at']);
        });
    }
};
