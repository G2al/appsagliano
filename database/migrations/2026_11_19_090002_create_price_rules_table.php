<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regole prezzo fisse: quando la sequenza di diciture fatte in una
     * giornata da un veicolo (eventualmente filtrato per bancali) coincide
     * con la sequenza della regola, i prezzi degli step sovrascrivono il
     * listino filiale per quelle posizioni. bancali nullo = vale per
     * tutti i veicoli.
     */
    public function up(): void
    {
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('bancali')->nullable();
            $table->timestamps();
        });

        Schema::create('price_rule_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->foreignId('goods_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->decimal('owner_price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['price_rule_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rule_steps');
        Schema::dropIfExists('price_rules');
    }
};
