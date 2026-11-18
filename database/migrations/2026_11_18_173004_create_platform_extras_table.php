<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diciture extra opzionali di una piattaforma (es. "Extra serale",
     * "Extra festivo"), selezionabili dall'autista in creazione viaggio,
     * ognuna con un importo fisso che si somma al prezzo autista del
     * viaggio.
     */
    public function up(): void
    {
        Schema::create('platform_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->unique(['platform_id', 'name']);
        });

        Schema::create('trip_platform_extra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_extra_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['trip_id', 'platform_extra_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_platform_extra');
        Schema::dropIfExists('platform_extras');
    }
};
