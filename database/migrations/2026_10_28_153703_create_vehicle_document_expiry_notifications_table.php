<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_document_expiry_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_document_id')
                ->constrained(indexName: 'veh_doc_exp_notif_veh_doc_id_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('threshold_days');
            $table->date('expiry_date');
            $table->timestamp('sent_at');

            $table->unique(
                ['vehicle_document_id', 'threshold_days', 'expiry_date'],
                'vehicle_doc_expiry_notifications_unique_alert'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_document_expiry_notifications');
    }
};
