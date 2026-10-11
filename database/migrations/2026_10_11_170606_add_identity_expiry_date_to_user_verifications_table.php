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
            $table->date('identity_expiry_date')->nullable()->after('identity_back_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_verifications', function (Blueprint $table) {
            $table->dropColumn('identity_expiry_date');
        });
    }
};
