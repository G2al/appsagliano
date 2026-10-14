<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_verifications', function (Blueprint $table) {
            $table->string('license_back_path')->nullable()->after('license_path');
            $table->string('cqc_back_path')->nullable()->after('cqc_path');
            $table->string('tachograph_front_path')->nullable()->after('cqc_expiry_date');
            $table->string('tachograph_back_path')->nullable()->after('tachograph_front_path');
            $table->date('tachograph_expiry_date')->nullable()->after('tachograph_back_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_verifications', function (Blueprint $table) {
            $table->dropColumn([
                'license_back_path',
                'cqc_back_path',
                'tachograph_front_path',
                'tachograph_back_path',
                'tachograph_expiry_date',
            ]);
        });
    }
};
