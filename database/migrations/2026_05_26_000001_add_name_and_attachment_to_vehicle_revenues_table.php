<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_revenues', function (Blueprint $table): void {
            $table->string('name')->default('Entrata veicolo')->after('date');
            $table->string('attachment_path')->nullable()->after('amount_inc_vat');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_revenues', function (Blueprint $table): void {
            $table->dropColumn([
                'name',
                'attachment_path',
            ]);
        });
    }
};
