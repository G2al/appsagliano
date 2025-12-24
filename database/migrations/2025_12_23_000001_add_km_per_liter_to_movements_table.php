<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $expression = 'CASE
                WHEN km_end IS NOT NULL
                    AND km_start IS NOT NULL
                    AND km_end >= km_start
                    AND liters IS NOT NULL
                    AND liters > 0
                THEN ROUND((km_end - km_start) / liters, 2)
                ELSE NULL
            END';

            $table
                ->decimal('km_per_liter', 10, 2)
                ->storedAs($expression)
                ->nullable()
                ->after('liters');
        });
    }

    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->dropColumn('km_per_liter');
        });
    }
};

