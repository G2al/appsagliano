<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('percentage', 5, 2)->default(22.00);
            $table->timestamps();
        });

        DB::table('vat_settings')->insert([
            'percentage' => 22.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_settings');
    }
};
