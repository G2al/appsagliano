<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guadagno del proprietario per questo viaggio: calcolato e salvato
     * come il prezzo autista, ma dal listino "owner_price" della filiale
     * (piu' eventuale supplemento scarichi della piattaforma). Non e'
     * mai visibile ai ruoli non-admin.
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('owner_price', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('owner_price');
        });
    }
};
