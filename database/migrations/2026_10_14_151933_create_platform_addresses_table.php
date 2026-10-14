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
        Schema::create('platform_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->string('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        DB::table('platforms')
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->orderBy('id')
            ->get(['id', 'address', 'latitude', 'longitude'])
            ->each(function ($platform) {
                DB::table('platform_addresses')->insert([
                    'platform_id' => $platform->id,
                    'address' => $platform->address,
                    'latitude' => $platform->latitude,
                    'longitude' => $platform->longitude,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('platforms', function (Blueprint $table) {
            $table->dropColumn(['address', 'latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        DB::table('platform_addresses')
            ->orderBy('platform_id')
            ->orderBy('id')
            ->get()
            ->groupBy('platform_id')
            ->each(function ($rows, $platformId) {
                $first = $rows->first();

                DB::table('platforms')->where('id', $platformId)->update([
                    'address' => $first->address,
                    'latitude' => $first->latitude,
                    'longitude' => $first->longitude,
                ]);
            });

        Schema::dropIfExists('platform_addresses');
    }
};
