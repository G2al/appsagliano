<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->string('company')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->dropColumn('company');
        });
    }
};
