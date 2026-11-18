<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scaglioni "a partire da tot scarichi, aggiungi tot euro al guadagno
     * del proprietario", configurati per ogni dicitura (goods_type) di
     * una piattaforma. Si applica solo a owner_price, mai al prezzo
     * autista.
     */
    public function up(): void
    {
        Schema::create('goods_type_discharge_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_scarichi');
            $table->decimal('bonus', 10, 2);
            $table->timestamps();

            $table->unique(['goods_type_id', 'min_scarichi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_type_discharge_bonuses');
    }
};
