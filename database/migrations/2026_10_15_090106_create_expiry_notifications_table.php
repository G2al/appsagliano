<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expiry_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_verification_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 20);
            $table->unsignedSmallInteger('threshold_days');
            $table->date('expiry_date');
            $table->timestamp('sent_at');

            $table->unique(
                ['user_verification_id', 'document_type', 'threshold_days', 'expiry_date'],
                'expiry_notifications_unique_alert'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiry_notifications');
    }
};
