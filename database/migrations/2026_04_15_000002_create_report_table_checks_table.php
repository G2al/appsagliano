<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_table_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('table_key', 191);
            $table->string('filter_key', 64);
            $table->string('row_key', 191);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'table_key', 'filter_key', 'row_key'],
                'report_table_checks_unique_scope'
            );
            $table->index(['user_id', 'table_key', 'filter_key'], 'report_table_checks_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_table_checks');
    }
};
