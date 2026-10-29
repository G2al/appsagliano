<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Listino prezzi di una filiale: un prezzo per "numero viaggio della
     * giornata" (1-6), usato per il calcolo automatico del prezzo bordero.
     */
    public function up(): void
    {
        Schema::create('filiale_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filiale_id')->constrained('filiali')->cascadeOnDelete();
            $table->unsignedTinyInteger('trip_order');
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['filiale_id', 'trip_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filiale_prices');
    }
};
