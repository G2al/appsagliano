<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toll_road_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('toll_road_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->index(['toll_road_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toll_road_expenses');
    }
};
