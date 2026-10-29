<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La "dicitura" non e' piu' un campo diretto su Trip: e' implicita
     * tramite filiale -> goods_type. Aggiunge "trip_order" (1-6, calcolato
     * al salvataggio: quanti bordero ha gia' fatto quell'autista quel
     * giorno) e "reviewed_at": da ora i revisionatori (modulo bordero)
     * controllano e accettano il record (con eventuale scansione
     * allegata), non modificano piu' il prezzo (calcolato dal listino in
     * base a trip_order, modificabile solo da admin).
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('goods_type');
            $table->unsignedTinyInteger('trip_order')->nullable()->after('filiale_id');
            $table->timestamp('reviewed_at')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('goods_type', 20)->default('secco');
            $table->dropColumn(['trip_order', 'reviewed_at']);
        });
    }
};
