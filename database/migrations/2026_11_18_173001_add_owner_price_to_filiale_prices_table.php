<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Secondo prezzo del listino filiale: quanto incassa il proprietario
     * per quel numero di viaggio, inserito a mano accanto al prezzo
     * autista gia' esistente (stessa riga, non un record separato).
     */
    public function up(): void
    {
        Schema::table('filiale_prices', function (Blueprint $table) {
            $table->decimal('owner_price', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('filiale_prices', function (Blueprint $table) {
            $table->dropColumn('owner_price');
        });
    }
};
