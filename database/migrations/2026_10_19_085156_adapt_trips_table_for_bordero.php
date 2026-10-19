<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trasforma "Viaggi" in "Bordero": via piattaforma/destinazioni/calcolo
     * km (tutta la parte di distanza stradale), dentro filiale + scarichi.
     *
     * I viaggi gia' esistenti vengono svuotati (decisione presa con il
     * cliente per il branch demo): non e' possibile valorizzare filiale_id
     * (obbligatorio) su righe vecchie che non hanno mai avuto una filiale.
     */
    public function up(): void
    {
        DB::table('trip_attachments')->delete();
        DB::table('trips')->delete();

        Schema::table('trips', function (Blueprint $table) {
            $table->dropForeign(['platform_id']);
            $table->dropForeign(['platform_address_id']);
            $table->dropColumn([
                'platform_id',
                'platform_address_id',
                'destinations',
                'distance_km',
                'distance_status',
                'distance_note',
                'distance_calculated_at',
                'distance_error_code',
                'distance_error_destination',
            ]);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('filiale_id')->after('user_id')->constrained('filiali')->restrictOnDelete();
            $table->integer('scarichi_positivi')->default(0)->after('goods_type');
            $table->integer('scarichi_negativi')->default(0)->after('scarichi_positivi');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropForeign(['filiale_id']);
            $table->dropColumn(['filiale_id', 'scarichi_positivi', 'scarichi_negativi']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('platform_id')->after('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('platform_address_id')->nullable()->after('platform_id')->constrained()->nullOnDelete();
            $table->json('destinations')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->string('distance_status')->nullable();
            $table->string('distance_note')->nullable();
            $table->timestamp('distance_calculated_at')->nullable();
            $table->string('distance_error_code')->nullable();
            $table->string('distance_error_destination')->nullable();
        });
    }
};
