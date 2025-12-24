<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('worker')->index()->after('password');
        });

        // Ensure at least one admin stays active: promote the first user if none is marked.
        $adminEmail = env('DEFAULT_ADMIN_EMAIL');

        if ($adminEmail) {
            DB::table('users')
                ->where('email', $adminEmail)
                ->update(['role' => 'admin']);
        } else {
            DB::table('users')
                ->orderBy('id')
                ->limit(1)
                ->update(['role' => 'admin']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
