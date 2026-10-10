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
        Schema::create('trip_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        DB::table('trips')
            ->whereNotNull('attachment_path')
            ->orderBy('id')
            ->get(['id', 'attachment_path'])
            ->each(function ($trip) {
                DB::table('trip_attachments')->insert([
                    'trip_id' => $trip->id,
                    'path' => $trip->attachment_path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('attachment_path')->nullable();
        });

        DB::table('trip_attachments')
            ->orderBy('trip_id')
            ->orderBy('id')
            ->get()
            ->groupBy('trip_id')
            ->each(function ($rows, $tripId) {
                DB::table('trips')->where('id', $tripId)->update([
                    'attachment_path' => $rows->first()->path,
                ]);
            });

        Schema::dropIfExists('trip_attachments');
    }
};
