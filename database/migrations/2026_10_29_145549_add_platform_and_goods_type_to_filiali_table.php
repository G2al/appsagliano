<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ogni filiale appartiene ora a UNA piattaforma e UNA dicitura precise
     * (stessa filiale "fisica" va reinserita piu' volte se serve per piu'
     * diciture). Svuota i viaggi/filiali demo esistenti: non e' possibile
     * assegnare piattaforma/dicitura a dati gia' esistenti senza saperli,
     * e siamo sul branch demo (dati fittizi, nessuna perdita reale).
     */
    public function up(): void
    {
        DB::table('trip_attachments')->delete();
        DB::table('trips')->delete();
        DB::table('filiali')->delete();

        Schema::table('filiali', function (Blueprint $table) {
            $table->foreignId('platform_id')->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_type_id')->after('platform_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('filiali', function (Blueprint $table) {
            $table->dropForeign(['platform_id']);
            $table->dropForeign(['goods_type_id']);
            $table->dropColumn(['platform_id', 'goods_type_id']);
        });
    }
};
