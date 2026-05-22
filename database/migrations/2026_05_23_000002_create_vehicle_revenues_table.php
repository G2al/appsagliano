<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_revenues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount_ex_vat', 10, 2);
            $table->decimal('vat_percentage', 5, 2)->default(22.00);
            $table->decimal('amount_inc_vat', 10, 2);
            $table->timestamps();

            $table->index(['vehicle_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_revenues');
    }
};
