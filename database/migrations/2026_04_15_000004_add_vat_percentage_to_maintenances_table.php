<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table): void {
            $table->decimal('vat_percentage', 5, 2)->nullable()->after('price');
        });

        $vatPercentage = DB::table('vat_settings')->value('percentage') ?? 22.00;

        DB::table('maintenances')
            ->whereNull('vat_percentage')
            ->update([
                'vat_percentage' => $vatPercentage,
            ]);
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table): void {
            $table->dropColumn('vat_percentage');
        });
    }
};
