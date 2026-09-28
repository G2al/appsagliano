<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->dateTime('date');
            $table->json('destinations');
            $table->string('goods_type', 20);
            $table->string('delivery_note_number', 50);
            $table->decimal('price', 12, 2)->nullable();
            $table->string('attachment_path');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'vehicle_id']);
            $table->index(['date', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
