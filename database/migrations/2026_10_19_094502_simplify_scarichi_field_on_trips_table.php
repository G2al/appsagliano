<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correzione: il bordero ha un solo numero di scarichi (intero positivo,
     * incrementato/decrementato con uno stepper lato frontend), non due
     * contatori separati positivi/negativi come creato per errore.
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedInteger('scarichi')->default(0)->after('goods_type');
        });

        DB::table('trips')->update([
            'scarichi' => DB::raw('scarichi_positivi'),
        ]);

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['scarichi_positivi', 'scarichi_negativi']);
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->integer('scarichi_positivi')->default(0)->after('goods_type');
            $table->integer('scarichi_negativi')->default(0)->after('scarichi_positivi');
        });

        DB::table('trips')->update([
            'scarichi_positivi' => DB::raw('scarichi'),
        ]);

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('scarichi');
        });
    }
};
