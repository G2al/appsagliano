<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('movement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        DB::table('movements')
            ->whereNotNull('photo_path')
            ->orderBy('id')
            ->get(['id', 'photo_path'])
            ->each(function ($movement) {
                DB::table('movement_attachments')->insert([
                    'movement_id' => $movement->id,
                    'path' => $movement->photo_path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('movements', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
        });

        DB::table('movement_attachments')
            ->orderBy('movement_id')
            ->orderBy('id')
            ->get()
            ->groupBy('movement_id')
            ->each(function ($rows, $movementId) {
                DB::table('movements')->where('id', $movementId)->update([
                    'photo_path' => $rows->first()->path,
                ]);
            });

        Schema::dropIfExists('movement_attachments');
    }
};
