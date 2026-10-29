<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Dicitura" (secco/freschi/frutta/surgelati...): appartiene a una
     * piattaforma, non e' piu' un valore fisso su Trip.
     */
    public function up(): void
    {
        Schema::create('goods_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['platform_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_types');
    }
};
