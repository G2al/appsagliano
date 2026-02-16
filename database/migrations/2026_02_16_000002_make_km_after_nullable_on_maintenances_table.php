<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->unsignedBigInteger('km_after')->nullable()->change();
        });

        DB::table('maintenances')
            ->where('km_after', 0)
            ->update(['km_after' => null]);
    }

    public function down(): void
    {
        DB::table('maintenances')
            ->whereNull('km_after')
            ->update(['km_after' => 0]);

        Schema::table('maintenances', function (Blueprint $table) {
            $table->unsignedBigInteger('km_after')->change();
        });
    }
};
