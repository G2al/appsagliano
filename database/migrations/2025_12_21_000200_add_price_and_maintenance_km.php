<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('km_after');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedBigInteger('maintenance_km')->default(0)->after('current_km');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropColumn('price');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('maintenance_km');
        });
    }
};
